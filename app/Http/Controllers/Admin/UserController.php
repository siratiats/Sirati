<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Controller;
use App\Models\JobMatch;
use App\Models\SubscriptionAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthorizesAdmin;

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'premium' => in_array($request->query('premium'), ['yes', 'no'], true) ? $request->query('premium') : '',
            'verified' => in_array($request->query('verified'), ['yes', 'no'], true) ? $request->query('verified') : '',
        ];
        $now = now();

        $users = $this->withActivityCounts(User::query())
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($filters['premium'] === 'yes', fn (Builder $query) => $this->wherePremium($query, $now))
            ->when($filters['premium'] === 'no', fn (Builder $query) => $query->whereNot(fn (Builder $query) => $this->wherePremium($query, $now)))
            ->when($filters['verified'] === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user = $this->withActivityCounts(User::query())->findOrFail($user->id);

        return view('admin.users.show', [
            'user' => $user,
            'auditLogs' => SubscriptionAuditLog::query()
                ->where('user_id', $user->id)
                ->latest()
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    private function withActivityCounts(Builder $query): Builder
    {
        return $query
            ->withCount(['cvAnalyses', 'generatedCvs'])
            ->addSelect([
                'job_matches_count' => JobMatch::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('job_matches.user_id', 'users.id'),
            ]);
    }

    /**
     * Mirrors User::isPremium(): premium_until wins when set, else the flag.
     */
    private function wherePremium(Builder $query, $now): void
    {
        $query->where(function (Builder $query) use ($now): void {
            // whereNotNull keeps NOT(...) two-valued for the "not premium" filter.
            $query->where(fn (Builder $query) => $query->whereNotNull('premium_until')->where('premium_until', '>', $now))
                ->orWhere(fn (Builder $query) => $query->whereNull('premium_until')->where('is_premium', true));
        });
    }
}
