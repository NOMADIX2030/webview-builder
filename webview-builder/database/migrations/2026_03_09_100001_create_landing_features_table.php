<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_features', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('icon')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        DB::table('landing_features')->insert([
            [
                'title' => '웹뷰 앱 빌더',
                'description' => '웹사이트를 Android·iOS 앱으로 쉽게 만들어보세요.',
                'url' => '/build/step1',
                'icon' => 'device-phone-mobile',
                'order' => 1,
                'visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_features');
    }
};
