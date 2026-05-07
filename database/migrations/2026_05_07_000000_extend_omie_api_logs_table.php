<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::table('omie_api_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'correlation_id')) {
                $table->uuid('correlation_id')->nullable()->after('id');
                $table->index('correlation_id');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('correlation_id');
                $table->index('tenant_id');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'status')) {
                $table->string('status', 20)->default('pending')->after('tenant_id');
                $table->index('status');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'attempt')) {
                $table->unsignedSmallInteger('attempt')->default(1)->after('status');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'omie_fault_code')) {
                $table->string('omie_fault_code')->nullable()->after('omie_status_message');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'omie_fault_string')) {
                $table->text('omie_fault_string')->nullable()->after('omie_fault_code');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'retryable')) {
                $table->boolean('retryable')->default(false)->after('omie_fault_string');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('omie_api_logs', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('retryable');
            }
        });

        \Illuminate\Support\Facades\Schema::table('omie_api_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->index(['app_key', 'method', 'status', 'created_at'], 'omie_logs_dashboard_idx');
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::table('omie_api_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropIndex('omie_logs_dashboard_idx');
            $table->dropColumn([
                'correlation_id',
                'tenant_id',
                'status',
                'attempt',
                'omie_fault_code',
                'omie_fault_string',
                'retryable',
                'finished_at',
            ]);
        });
    }
};
