<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInstitutionKnowledgeBaseItemPivotTable extends Migration
{
    public function up(): void
    {
        Schema::create('institution_knowledge_base_item', function (Blueprint $table) {
            $table->foreignId('knowledge_base_item_id')->references('id')->on('knowledge_base_items');
            $table->foreignId('institution_id')->references('id')->on('institutions');
        });
    }
}
