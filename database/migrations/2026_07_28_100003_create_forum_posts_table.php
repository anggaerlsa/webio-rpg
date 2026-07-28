<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pesan dalam topik. Pesan pertama (`is_first`) = isi topik itu sendiri, agar
// edit/kutip/apresiasi memakai satu jalur kode. Hapus = soft delete (jejak moderasi).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('reply_to_id')->nullable()->constrained('forum_posts')->nullOnDelete();
            $table->boolean('is_first')->default(false);
            $table->unsignedInteger('appreciations')->default(0); // cache jumlah apresiasi
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['topic_id', 'id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
