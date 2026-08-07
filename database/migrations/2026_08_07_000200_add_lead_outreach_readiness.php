<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'outreach_readiness')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->string('outreach_readiness', 24)->default('needs_enrichment')->after('status')->index();
            });
        }

        DB::table('leads')
            ->where(function ($query): void {
                $query->whereNotNull('email')->where('email', '!=', '')
                    ->orWhere(function ($q): void { $q->whereNotNull('phone')->where('phone', '!=', ''); })
                    ->orWhere(function ($q): void { $q->whereNotNull('linkedin_url')->where('linkedin_url', '!=', ''); });
            })
            ->update(['outreach_readiness' => 'ready']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'outreach_readiness')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->dropColumn('outreach_readiness');
            });
        }
    }
};
