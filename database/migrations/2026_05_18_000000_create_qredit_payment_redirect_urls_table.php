<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qredit_payment_redirect_urls', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('payment_reference')->index();

            $table->text('success_url')->nullable();
            $table->text('cancel_url')->nullable();
            $table->text('failure_url')->nullable();
            $table->text('pending_url')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'payment_reference'], 'qredit_redirect_tenant_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qredit_payment_redirect_urls');
    }
};
