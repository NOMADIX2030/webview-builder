<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('landing_features')) {
            return;
        }

        $exists = DB::table('landing_features')->where('url', '/chat')->exists();
        if ($exists) {
            return;
        }

        DB::table('landing_features')->insert([
            'title' => 'AI 채팅',
            'description' => 'ChatGPT 스타일 AI와 대화하세요.',
            'url' => '/chat',
            'icon' => 'chat-bubble-left-right',
            'order' => 2,
            'visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('landing_features')) {
            DB::table('landing_features')->where('url', '/chat')->delete();
        }
    }
};
