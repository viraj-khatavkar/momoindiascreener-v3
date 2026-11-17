<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $names = DB::table('backtest_nse_instruments')->whereNotNull('industry')
                ->distinct()->orderBy('industry')->pluck('industry');

            foreach ($names as $name) {
                $id = DB::table('industries')->where('name', $name)->value('id');
                if ($id === null) {
                    $baseSlug = Str::limit(Str::slug($name), 240, '') ?: 'industry';
                    $slug = $baseSlug;
                    for ($suffix = 2; DB::table('industries')->where('slug', $slug)->exists(); $suffix++) {
                        $slug = $baseSlug.'-'.$suffix;
                    }
                    $id = DB::table('industries')->insertGetId([
                        'name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                DB::table('backtest_nse_instruments')->where('industry', $name)->update(['industry_id' => $id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('backtest_nse_instruments')->update(['industry' => null]);
            foreach (DB::table('industries')->get(['id', 'name']) as $industry) {
                DB::table('backtest_nse_instruments')->where('industry_id', $industry->id)->update(['industry' => $industry->name]);
            }
        });
    }
};
