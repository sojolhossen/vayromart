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
        // Add chatbot settings to general_settings if not exists
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'chatbot_enabled')) {
                $table->tinyInteger('chatbot_enabled')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'chatbot_settings')) {
                $table->text('chatbot_settings')->nullable();
            }
        });

        // Chatbot Conversations table
        if (!Schema::hasTable('chatbot_conversations')) {
            Schema::create('chatbot_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('session_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        // Chatbot Messages table
        if (!Schema::hasTable('chatbot_messages')) {
            Schema::create('chatbot_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('sender', 10); // 'user' or 'bot'
                $table->text('message');
                $table->timestamps();

                $table->foreign('conversation_id')->references('id')->on('chatbot_conversations')->onDelete('cascade');
            });
        }

        // Chatbot Knowledge base
        if (!Schema::hasTable('chatbot_knowledge')) {
            Schema::create('chatbot_knowledge', function (Blueprint $table) {
                $table->id();
                $table->string('question'); // Topic / Keyword
                $table->text('answer'); // Dynamic text response context
                $table->tinyInteger('is_active')->default(1);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_conversations');
        Schema::dropIfExists('chatbot_knowledge');
        
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'chatbot_enabled')) {
                $table->dropColumn('chatbot_enabled');
            }
            if (Schema::hasColumn('general_settings', 'chatbot_settings')) {
                $table->dropColumn('chatbot_settings');
            }
        });
    }
};
