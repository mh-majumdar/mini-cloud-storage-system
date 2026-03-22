<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('file_stores', function (Blueprint $table) {
            $table->id();
            $table->string('file_hash', 64)->unique();
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('ref_count')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('file_stores');
    }
};
