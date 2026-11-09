<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_number', 30)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->string('water_type', 80);
            $table->unsignedInteger('quantity_litres')->nullable();
            $table->unsignedInteger('requested_capacity')->nullable();
            $table->text('delivery_address');
            $table->string('area', 120)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time_slot', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('alternate_contact', 20)->nullable();
            $table->enum('status', ['new', 'contacted', 'quotation_sent', 'converted', 'closed', 'cancelled'])->default('new')->index();
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->text('quotation_details')->nullable();
            $table->timestamp('customer_accepted_at')->nullable();
            $table->unsignedBigInteger('converted_booking_id')->nullable();
            $table->string('close_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enquiry_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('note'); // note|call|quotation|status
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('borewell_id')->nullable()->constrained()->nullOnDelete();
            $table->string('water_type', 80);
            $table->unsignedInteger('quantity_litres');
            $table->unsignedInteger('requested_capacity')->nullable();
            $table->text('delivery_address');
            $table->string('area', 120)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('preferred_date')->index();
            $table->string('preferred_time_slot', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('pricing_type', 20)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid')->index();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->text('quotation_notes')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('customer_accepted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->decimal('cancellation_fee', 12, 2)->default(0);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('delivered_litres')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tanker_pricing_id')->nullable()->constrained('tanker_pricing')->nullOnDelete();
            $table->enum('pricing_type', ['per_litre', 'per_trip']);
            $table->decimal('rate_snapshot', 12, 4);
            $table->unsignedInteger('quantity_litres');
            $table->unsignedInteger('billable_litres');
            $table->unsignedInteger('vehicle_capacity')->nullable();
            $table->unsignedSmallInteger('trips')->default(1);
            $table->unsignedSmallInteger('calculated_trips')->default(1);
            $table->string('trip_override_reason')->nullable();
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('driver_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_user_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->enum('assignment_status', ['assigned', 'accepted', 'rejected', 'in_transit', 'arrived', 'delivered', 'completed', 'withdrawn', 'superseded', 'cancelled'])->default('assigned')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('proof_notes')->nullable();
            $table->unsignedInteger('delivered_litres')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_accuracy_m', 8, 2)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();
            $table->index('vehicle_id');
            $table->index(['booking_id', 'assignment_status']);
        });

        Schema::create('tanker_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('booking_assignments')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->index(['assignment_id', 'recorded_at']);
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'upi', 'bank_transfer', 'other']);
            $table->string('transaction_reference', 100)->nullable();
            $table->enum('status', ['received', 'void'])->default('received');
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->string('type', 50)->index();
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notifications', 'payments', 'booking_status_history', 'tanker_locations', 'booking_assignments', 'booking_items', 'bookings', 'enquiry_notes', 'enquiries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
