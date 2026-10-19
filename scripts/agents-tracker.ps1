# Builds a self-refreshing HTML tracker of the delegated coding agents.
# Reads: .agents/logs/parallel-*/summary.json (finished runs), .agents/tracker/plan.json (working plan),
#        .agents/tracker/adhoc.json (read-only audits and one-off runs), live agent processes.
# Writes: .agents/logs/tracker.html (git-ignored). Open it in a browser; it reloads itself every 20 s.
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\agents-tracker.ps1            (build once)
#         powershell -ExecutionPolicy Bypass -File scripts\agents-tracker.ps1 -Watch     (rebuild every 15 s)
param([switch]$Watch, [int]$IntervalSeconds = 15, [int]$StallMinutes = 10)

$root    = Split-Path $PSScriptRoot -Parent
$logs    = Join-Path $root '.agents\logs'
$trk     = Join-Path $root '.agents\tracker'
$out     = Join-Path $logs 'tracker.html'
$cpuFile = Join-Path $trk 'cpu.json'
New-Item -ItemType Directory -Force $trk, $logs | Out-Null

function Esc([object]$s) { [System.Net.WebUtility]::HtmlEncode([string]$s) }
function Clip([string]$s, [int]$n = 600) { if ($s.Length -gt $n) { $s.Substring(0, $n) + ' ...' } else { $s } }

# cleanup-worktrees.ps1 only deletes merged agent/* branches, so a missing branch on a finished run means it landed
function Test-Branch([string]$b) { & git -C $root show-ref --verify --quiet "refs/heads/$b" 2>$null; return ($LASTEXITCODE -eq 0) }

function Get-StopReason($e) {
  $why = @()
  if ($e.status -eq 'TIMEOUT')        { $why += 'Timed out (the coder was still working when the limit hit)' }
  if ($e.status -eq 'QUOTA')          { $why += 'Quota or rate limit on every option' }
  if ($e.status -eq 'NO-CHANGES')     { $why += 'Finished with no file changes (blocked or nothing to do)' }
  if ($e.status -eq 'STALL')          { $why += 'Killed: no CPU or output for too long' }
  if ($e.status -match 'SAFETY')      { $why += 'Safety guard violation: edit to protected files reverted' }
  if ("$($e.note)" -match 'quota|usage limit|rate limit') { $why += "Hit a plan/quota limit, fallback used: $($e.note)" }
  elseif ("$($e.note)") { $why += "Note: $($e.note)" }
  if ("$($e.check)" -match '^FAIL')   { $why += 'Acceptance check failed' }
  if ("$($e.review)" -cmatch '] CHANGES'){ $why += 'A reviewer asked for changes' }
  if ("$($e.status)" -eq 'EMPTY')     { $why += 'Agent returned nothing' }
  , $why
}

function Build {
  # ---- finished runs ----
  $runs = @()
  foreach ($d in Get-ChildItem $logs -Directory -Filter 'parallel-*' -ErrorAction SilentlyContinue) {
    $f = Join-Path $d.FullName 'summary.json'
    if (-not (Test-Path $f)) { continue }
    try { $j = Get-Content $f -Raw | ConvertFrom-Json } catch { continue }
    foreach ($e in @($j)) {
      $e | Add-Member -NotePropertyName stamp -NotePropertyValue ($d.Name -replace '^parallel-', '') -Force
      $runs += $e
    }
  }
  $runs = $runs | Sort-Object stamp -Descending

  # ---- ad-hoc ----
  $adhoc = @()
  $af = Join-Path $trk 'adhoc.json'
  if (Test-Path $af) { try { $adhoc = @(Get-Content $af -Raw | ConvertFrom-Json | ForEach-Object { $_ }) } catch {} }

  # ---- working plan ----
  $plan = $null
  $pf = Join-Path $trk 'plan.json'
  if (Test-Path $pf) { try { $plan = Get-Content $pf -Raw | ConvertFrom-Json } catch {} }

  # ---- live processes with idle detection ----
  $prev = @{}
  if (Test-Path $cpuFile) { try { (Get-Content $cpuFile -Raw | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $prev[$_.Name] = $_.Value } } catch {} }
  $now = Get-Date
  $live = @(); $store = @{}
  $names = 'codex', 'agy', 'grok', 'copilot-runtime', 'muse-bin*', 'muse'
  foreach ($p in Get-Process -ErrorAction SilentlyContinue | Where-Object { $n = $_.ProcessName; $names | Where-Object { $n -like $_ } }) {
    if ($p.ProcessName -eq 'codex' -and $p.MainWindowTitle -eq '' -and $p.StartTime -lt (Get-Date).AddHours(-6)) { continue }   # long-lived app helper
    $cpu = [math]::Round($p.CPU, 1)
    $key = "$($p.Id)"
    $since = $now
    if ($prev.ContainsKey($key) -and $prev[$key].cpu -eq $cpu) { $since = [datetime]$prev[$key].since }
    $store[$key] = @{ cpu = $cpu; since = $since.ToString('o') }
    $idle = [math]::Round(($now - $since).TotalMinutes, 1)
    $live += [pscustomobject]@{ name = $p.ProcessName; pid = $p.Id; started = $p.StartTime.ToString('MM-dd HH:mm'); cpu = $cpu; idle = $idle; stall = ($idle -ge $StallMinutes) }
  }
  ($store | ConvertTo-Json -Depth 4) | Set-Content $cpuFile -Encoding utf8

  # ---- stopped cases: open vs resolved ----
  $resolvedNotes = @{}
  $rf = Join-Path $trk 'resolved.json'
  if (Test-Path $rf) { try { (Get-Content $rf -Raw | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $resolvedNotes[$_.Name] = $_.Value } } catch {} }
  $mergedBranches = @(& git -C $root branch --merged main 2>$null | ForEach-Object { $_.Trim(' *+') })
  $stopped = @(); $resolved = @()
  foreach ($e in $runs) {
    $w = Get-StopReason $e
    if ($w.Count -eq 0) { continue }
    $item = [pscustomobject]@{ id = $e.id; coder = $e.coder; status = $e.status; when = $e.stamp; why = ($w -join ' | '); summary = Clip ("$($e.summary)") 260; fix = '' }
    $later = $runs | Where-Object { $_.id -eq $e.id -and $_.stamp -gt $e.stamp -and $_.status -eq 'CHANGED' -and "$($_.check)" -notmatch '^FAIL' } | Select-Object -First 1
    if ($resolvedNotes.ContainsKey([string]$e.id)) { $item.fix = [string]$resolvedNotes[[string]$e.id]; $resolved += $item }
    elseif ($later) { $item.fix = "Fixed by a later run of the same task ($($later.stamp))."; $resolved += $item }
    elseif ($e.status -eq 'CHANGED' -and "$($e.branch)" -and (($mergedBranches -contains "$($e.branch)") -or -not (Test-Branch "$($e.branch)"))) { $item.fix = 'Branch merged into main (after follow-ups) and cleaned up.'; $resolved += $item }
    else { $stopped += $item }
  }
  foreach ($a in $adhoc) { if ($a.status -in 'STOPPED', 'FAILED', 'QUOTA', 'EMPTY', 'TIMEOUT') { $stopped += [pscustomobject]@{ id = $a.id; coder = $a.coder; status = $a.status; when = $a.started; why = "$($a.note)"; summary = Clip ("$($a.result)") 260; fix = '' } } }
  foreach ($l in $live | Where-Object stall) { $stopped += [pscustomobject]@{ id = "process $($l.name) #$($l.pid)"; coder = $l.name; status = 'POSSIBLE STALL'; when = $l.started; why = "No CPU change for $($l.idle) min (limit $StallMinutes)"; summary = ''; fix = '' } }

  $running = @($adhoc | Where-Object status -eq 'RUNNING').Count
  $queued  = @($adhoc | Where-Object status -eq 'QUEUED').Count
  $ok      = @($runs | Where-Object { $_.status -eq 'CHANGED' -and "$($_.check)" -notmatch '^FAIL' }).Count

  # ---- html ----
  $badge = { param($s) $c = switch -Regex ($s) { '^(CHANGED|DONE|OK|PASS)$' { 'ok' } '^(RUNNING)$' { 'run' } '^(QUEUED)$' { 'que' } '^(NO-CHANGES)$' { 'warn' } default { 'bad' } }; "<span class='b $c'>$(Esc $s)</span>" }

  $sb = New-Object System.Text.StringBuilder
  [void]$sb.Append("<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><meta http-equiv='refresh' content='20'><title>Agent tracker</title><style>")
  [void]$sb.Append(@'
:root{--bg:#f6f7f9;--card:#fff;--ink:#14202b;--mut:#5b6b7a;--line:#dde3ea;--ok:#0b7a53;--okb:#dff5ea;--run:#0b5fa5;--runb:#dcecfb;--que:#5b4bb0;--queb:#ebe7fa;--warn:#8a5a00;--warnb:#fdf0d3;--bad:#a4262c;--badb:#fde4e4}
@media(prefers-color-scheme:dark){:root{--bg:#0f151b;--card:#18212a;--ink:#e8eef4;--mut:#9fb0bf;--line:#2a3743;--ok:#5fd6a1;--okb:#12382b;--run:#7cbcf2;--runb:#102f4a;--que:#b7aaf5;--queb:#2a2350;--warn:#f0c36a;--warnb:#3b2d0c;--bad:#ff9a9f;--badb:#43171a}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,Segoe UI,sans-serif}
main{max-width:1180px;margin:0 auto;padding:20px 16px 60px}h1{font-size:22px;margin:0 0 2px}h2{font-size:16px;margin:28px 0 10px}.sub{color:var(--mut);margin:0 0 16px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:12px 14px}
.card b{display:block;font-size:26px}.card span{color:var(--mut)}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:10px;overflow:hidden}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);background:color-mix(in srgb,var(--card) 90%,var(--line))}
tr:last-child td{border-bottom:0}.b{display:inline-block;padding:1px 8px;border-radius:99px;font-size:12px;font-weight:600;white-space:nowrap}
.ok{background:var(--okb);color:var(--ok)}.run{background:var(--runb);color:var(--run)}.que{background:var(--queb);color:var(--que)}.warn{background:var(--warnb);color:var(--warn)}.bad{background:var(--badb);color:var(--bad)}
.mut{color:var(--mut)}.small{font-size:12px}details summary{cursor:pointer;color:var(--run)}pre{white-space:pre-wrap;margin:6px 0 0;font:12px/1.45 ui-monospace,Consolas,monospace;background:color-mix(in srgb,var(--card) 92%,var(--line));padding:8px;border-radius:6px}
.stop{border-left:4px solid var(--bad)}.empty{padding:14px;background:var(--card);border:1px dashed var(--line);border-radius:10px;color:var(--mut)}
.wrap{overflow-x:auto}input{padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink);width:100%;max-width:320px}
'@)
  [void]$sb.Append("</style></head><body><main>")
  [void]$sb.Append("<h1>Agent tracker</h1><p class='sub'>Updated $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') &middot; auto-refresh 20 s &middot; source: .agents/logs, .agents/tracker</p>")
  [void]$sb.Append("<div class='cards'><div class='card'><b>$running</b><span>running now</span></div><div class='card'><b>$queued</b><span>queued</span></div><div class='card'><b style='color:var(--bad)'>$($stopped.Count)</b><span>open problems</span></div><div class='card'><b style='color:var(--ok)'>$ok</b><span>runs finished OK</span></div><div class='card'><b>$($runs.Count)</b><span>runs recorded</span></div></div>")

  # stopped
  [void]$sb.Append("<h2>Open problems: stopped cases and why</h2>")
  if ($stopped.Count -eq 0) { [void]$sb.Append("<div class='empty'>Nothing stopped.</div>") }
  else {
    [void]$sb.Append("<div class='wrap'><table><tr><th>Task</th><th>Agent</th><th>Status</th><th>When</th><th>Why it stopped</th><th>Last words</th></tr>")
    foreach ($s in $stopped) { [void]$sb.Append("<tr class='stop'><td><b>$(Esc $s.id)</b></td><td>$(Esc $s.coder)</td><td>$(& $badge $s.status)</td><td class='mut small'>$(Esc $s.when)</td><td>$(Esc $s.why)</td><td class='small mut'>$(Esc $s.summary)</td></tr>") }
    [void]$sb.Append("</table></div>")
  }

  # working plan
    if ($resolved.Count) {
    [void]$sb.Append("<details><summary>Resolved earlier ($($resolved.Count)): stopped, then fixed</summary><div class='wrap'><table><tr><th>Task</th><th>Agent</th><th>Status then</th><th>When</th><th>What happened</th><th>How it was resolved</th></tr>")
    foreach ($s in $resolved) { [void]$sb.Append("<tr><td><b>$(Esc $s.id)</b></td><td>$(Esc $s.coder)</td><td>$(& $badge $s.status)</td><td class='mut small'>$(Esc $s.when)</td><td>$(Esc $s.why)</td><td>$(Esc $s.fix)</td></tr>") }
    [void]$sb.Append("</table></div></details>")
  }

  [void]$sb.Append("<h2>Working plan (who does what)</h2><div class='wrap'><table><tr><th>Agent</th><th>Model</th><th>Role</th><th>Current assignment</th><th>Can</th><th>Limits seen</th></tr>")
  foreach ($a in @($plan.agents)) { [void]$sb.Append("<tr><td><b>$(Esc $a.agent)</b></td><td class='small'>$(Esc $a.model)</td><td>$(Esc $a.role)</td><td>$(Esc $a.assignment)</td><td class='small'>$(Esc $a.can)</td><td class='small mut'>$(Esc $a.cannot)</td></tr>") }
  [void]$sb.Append("</table></div>")

  # ad-hoc
  if ($adhoc.Count) {
    [void]$sb.Append("<h2>Current work (audits and one-off runs)</h2><div class='wrap'><table><tr><th>Task</th><th>Agent</th><th>Status</th><th>Started</th><th>What</th><th>Note</th></tr>")
    foreach ($a in $adhoc) { [void]$sb.Append("<tr><td><b>$(Esc $a.id)</b></td><td>$(Esc $a.coder)</td><td>$(& $badge $a.status)</td><td class='mut small'>$(Esc $a.started)</td><td>$(Esc $a.task)</td><td class='small mut'>$(Esc $a.note) $(Esc $a.result)</td></tr>") }
    [void]$sb.Append("</table></div>")
  }

  # live
  [void]$sb.Append("<h2>Live agent processes</h2>")
  if ($live.Count -eq 0) { [void]$sb.Append("<div class='empty'>No agent process running.</div>") }
  else {
    [void]$sb.Append("<div class='wrap'><table><tr><th>Process</th><th>PID</th><th>Started</th><th>CPU s</th><th>Idle (no CPU change)</th><th>State</th></tr>")
    foreach ($l in $live) { $st = if ($l.stall) { "<span class='b bad'>possible stall</span>" } elseif ($l.idle -ge 3) { "<span class='b warn'>quiet</span>" } else { "<span class='b run'>working</span>" }; [void]$sb.Append("<tr><td>$(Esc $l.name)</td><td>$($l.pid)</td><td class='small'>$(Esc $l.started)</td><td>$($l.cpu)</td><td>$($l.idle) min</td><td>$st</td></tr>") }
    [void]$sb.Append("</table></div>")
  }

  # history
  [void]$sb.Append("<h2>Run history</h2><p><input id='q' placeholder='Filter (task, agent, status)...' oninput='f()'></p><div class='wrap'><table id='h'><tr><th>Task</th><th>Agent</th><th>Status</th><th>Check</th><th>Diff</th><th>Risk</th><th>Time (s)</th><th>Details</th></tr>")
  foreach ($e in $runs) {
    $t = $e.timings; $tt = if ($t) { "setup $($t.setup) &middot; coder $($t.coder) &middot; check $($t.check) &middot; review $($t.review)" } else { '' }
    $chk = if ("$($e.check)" -match '^FAIL') { "<span class='b bad'>FAIL</span>" } elseif ("$($e.check)" -match 'PASS') { "<span class='b ok'>PASS</span>" } else { '<span class="mut">-</span>' }
    $det = "<details><summary>open</summary><pre>$(Esc (Clip "$($e.summary)" 900))</pre><pre>Review: $(Esc (Clip "$($e.review)" 900))</pre><pre>Branch: $(Esc $e.branch)`nFiles: $(Esc (Clip "$($e.files)" 500))`nLearnings: $(Esc (Clip "$($e.learnings)" 500))`nNote: $(Esc $e.note)</pre></details>"
    [void]$sb.Append("<tr><td><b>$(Esc $e.id)</b><div class='mut small'>$(Esc $e.stamp)</div></td><td>$(Esc $e.coder)</td><td>$(& $badge $e.status)</td><td>$chk</td><td class='small'>$(Esc $e.diff)</td><td>$(Esc $e.risk)</td><td class='small mut'>$tt</td><td>$det</td></tr>")
  }
  [void]$sb.Append("</table></div><script>function f(){var q=document.getElementById('q').value.toLowerCase();document.querySelectorAll('#h tr').forEach(function(r,i){if(i)r.style.display=r.textContent.toLowerCase().includes(q)?'':'none'})}</script></main></body></html>")
  Set-Content -Path $out -Value $sb.ToString() -Encoding utf8
  Write-Host ("tracker: {0} runs, {1} stopped, {2} running -> {3}" -f $runs.Count, $stopped.Count, $running, $out)
}

if ($Watch) { while ($true) { Build; Start-Sleep -Seconds $IntervalSeconds } } else { Build }
