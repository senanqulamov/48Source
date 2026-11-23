<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Part 2: requisitions, contracts, receipts, invoices, supplier 360, field assessments, RFI, Q&A, auctions, best-and-final rounds, messages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('requester_id')->constrained('users');
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->text('justification')->nullable();
            $t->date('needed_by')->nullable();
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('total_cents')->default(0);
            $t->string('status', 20)->default('draft')->index(); // draft, pending, approved, rejected, converted, cancelled
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        Schema::create('requisition_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->bigInteger('quantity_milli');
            $t->string('unit', 20);
            $t->bigInteger('est_price_cents')->nullable();
            $t->bigInteger('total_cents')->default(0);
            $t->timestamps();
        });
        Schema::create('requisition_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('step');
            $t->string('role', 30);
            $t->string('label');
            $t->string('status', 20)->default('waiting'); // waiting, pending, approved, rejected
            $t->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('comment')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('owner_id')->constrained('users');
            $t->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('status', 20)->default('draft')->index(); // draft, active, expired, terminated
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('ceiling_cents')->nullable();
            $t->bigInteger('released_cents')->default(0);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('payment_terms')->nullable();
            $t->text('terms')->nullable();
            $t->text('end_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('unit', 20);
            $t->bigInteger('unit_price_cents');
            $t->timestamps();
        });
        Schema::create('contract_releases', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('contract_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('status', 20)->default('issued'); // issued, accepted, delivered, cancelled
            $t->bigInteger('total_cents')->default(0);
            $t->date('needed_by')->nullable();
            $t->string('note')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_release_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_release_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contract_item_id')->constrained();
            $t->string('name');
            $t->string('unit', 20);
            $t->bigInteger('quantity_milli');
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('total_cents');
            $t->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('purchase_order_id')->constrained();
            $t->foreignId('received_by')->constrained('users');
            $t->date('received_on');
            $t->string('note')->nullable();
            $t->timestamps();
        });
        Schema::create('goods_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')->constrained();
            $t->bigInteger('accepted_milli');
            $t->bigInteger('rejected_milli')->default(0);
            $t->string('note')->nullable();
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->string('supplier_ref', 60);
            $t->foreignId('purchase_order_id')->constrained();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('submitted_by')->constrained('users');
            $t->string('status', 20)->default('submitted')->index(); // matched, disputed, approved, paid, rejected
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('total_cents')->default(0);
            $t->date('issued_on');
            $t->date('due_on')->nullable();
            $t->json('issues')->nullable();
            $t->text('decision_note')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['supplier_id', 'supplier_ref']);
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')->constrained();
            $t->bigInteger('quantity_milli');
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('total_cents');
            $t->timestamps();
        });

        Schema::create('supplier_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('title');
            $t->string('path')->nullable();
            $t->string('original_name')->nullable();
            $t->date('expires_on')->nullable();
            $t->string('status', 20)->default('pending'); // pending, verified, rejected
            $t->foreignId('uploaded_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('supplier_scorecards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('period', 10);
            $t->unsignedTinyInteger('quality');
            $t->unsignedTinyInteger('delivery');
            $t->unsignedTinyInteger('price');
            $t->unsignedTinyInteger('service');
            $t->unsignedSmallInteger('score_tenths');
            $t->text('comment')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['supplier_id', 'period']);
        });
        Schema::create('supplier_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('detail')->nullable();
            $t->date('due_on');
            $t->string('status', 20)->default('open'); // open, done
            $t->foreignId('owner_id')->constrained('users');
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
        });

        Schema::create('field_assessments', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title');
            $t->json('checklist');
            $t->json('answers')->nullable();
            $t->text('findings')->nullable();
            $t->string('status', 20)->default('assigned')->index(); // assigned, in_progress, submitted, approved, rejected
            $t->date('due_on')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('rfis', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('buyer_id')->constrained('users');
            $t->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->json('questions');
            $t->string('status', 20)->default('draft')->index(); // draft, open, closed
            $t->timestamp('deadline_at')->nullable();
            $t->timestamps();
        });
        Schema::create('rfi_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfi_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained();
            $t->string('status', 20)->default('invited'); // invited, answered, declined
            $t->json('answers')->nullable();
            $t->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('answered_at')->nullable();
            $t->timestamps();
            $t->unique(['rfi_id', 'supplier_id']);
        });
        Schema::create('rfq_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('asked_by')->constrained('users');
            $t->text('question');
            $t->text('answer')->nullable();
            $t->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('answered_at')->nullable();
            $t->timestamps();
        });

        Schema::create('auctions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('opened_by')->constrained('users');
            $t->string('status', 20)->default('live'); // live, ended, cancelled
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->unsignedInteger('min_step_bp')->default(50); // smallest improvement, in hundredths of a percent
            $t->unsignedInteger('extend_minutes')->default(2);
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
        });
        Schema::create('auction_bids', function (Blueprint $t) {
            $t->id();
            $t->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->bigInteger('total_cents');
            $t->timestamps();
            $t->index(['auction_id', 'total_cents']);
        });
        Schema::create('bafo_rounds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('round');
            $t->foreignId('opened_by')->constrained('users');
            $t->string('status', 20)->default('open'); // open, closed
            $t->timestamp('deadline_at');
            $t->text('message')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->unique(['rfq_id', 'round']);
        });

        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('subject');
            $t->nullableMorphs('subject_ref');
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('conversation_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('message_id')->default(0); // the newest message this user has seen
            $t->timestamp('read_at');
            $t->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['conversation_reads', 'messages', 'conversations', 'bafo_rounds', 'auction_bids', 'auctions', 'rfq_questions', 'rfi_responses', 'rfis', 'field_assessments', 'supplier_actions', 'supplier_scorecards', 'supplier_documents', 'invoice_items', 'invoices', 'goods_receipt_items', 'goods_receipts', 'contract_release_items', 'contract_releases', 'contract_items', 'contracts', 'requisition_approvals', 'requisition_items', 'requisitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
