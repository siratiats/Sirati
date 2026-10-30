<?php

use App\Services\AtsScoringService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job match is coverage of the job description's terms, so an analysis
 * without one has none (issue #16). Records where the keywords came from and
 * clears the category-list coverage older rows stored as their job match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->unsignedSmallInteger('job_match')->nullable()->change();
            $table->string('keyword_source', 20)->nullable()->after('job_match');
        });

        $scorer = app(AtsScoringService::class);

        DB::table('cv_analyses')->select(['id', 'job_description_input'])->chunkById(200, function ($rows) use ($scorer): void {
            foreach ($rows as $row) {
                $jobDescription = trim((string) $row->job_description_input);
                $fromJobDescription = $jobDescription !== '' && $scorer->extractJobTerms($jobDescription) !== [];

                DB::table('cv_analyses')->where('id', $row->id)->update($fromJobDescription
                    ? ['keyword_source' => 'job_description']
                    : ['keyword_source' => 'category', 'job_match' => null]);
            }
        });
    }

    public function down(): void
    {
        DB::table('cv_analyses')->whereNull('job_match')->update(['job_match' => 0]);

        Schema::table('cv_analyses', function (Blueprint $table) {
            $table->dropColumn('keyword_source');
            $table->unsignedSmallInteger('job_match')->nullable(false)->change();
        });
    }
};
