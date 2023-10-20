<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('pronouns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};