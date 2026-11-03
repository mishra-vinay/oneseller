<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->timestamp('reminded_at')->nullable()->after('token'));
        Schema::table('users', fn (Blueprint $table) => $table->boolean('cart_reminders')->default(true)->after('status'));

        // Content pages (about, FAQ, policies) and blog posts, written in Markdown.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');
            $table->string('cover_image_path')->nullable();
            $table->boolean('show_in_footer')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('cart_reminders'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
    }
};
