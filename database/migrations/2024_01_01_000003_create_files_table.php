<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('file_store_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'file_name', 'deleted_at'], 'unique_active_file');
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
};
