<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category');
            $table->unsignedInteger('price'); // whole naira
            $table->unsignedInteger('stock');
            $table->unsignedInteger('reorder_level')->default(10);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('customer_name');
            $table->string('city');
            $table->string('status'); // pending, paid, shipped, delivered, cancelled
            $table->unsignedInteger('total');
            $table->timestamp('placed_at');
            $table->timestamp('shipped_at')->nullable();
            $table->string('courier')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
        });

        Schema::create('restock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending'); // a manager approves or rejects
            $table->string('requested_by')->default('assistant');
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->unsignedSmallInteger('steps')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('restock_requests');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
