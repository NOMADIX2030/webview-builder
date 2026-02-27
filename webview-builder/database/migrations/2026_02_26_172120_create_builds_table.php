<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 20)->default('queued');
            $table->string('app_type', 20)->default('webview');
            $table->string('web_url', 500);
            $table->string('app_name');
            $table->string('package_id');
            $table->string('version_name', 50)->default('1.0.0');
            $table->unsignedInteger('version_code')->default(1);
            $table->string('privacy_policy_url', 500);
            $table->string('support_url', 500);
            $table->string('app_icon_path', 500);
            $table->string('splash_image_path', 500)->nullable();
            $table->json('config_json')->nullable();
            $table->string('apk_path', 500)->nullable();
            $table->string('ipa_path', 500)->nullable();
            $table->string('keystore_path', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
