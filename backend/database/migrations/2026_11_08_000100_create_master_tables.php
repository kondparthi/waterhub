<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|int|decimal|bool|json
            $table->string('label')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('water_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('alternate_mobile', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('area', 120)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->default('Home');
            $table->text('address');
            $table->string('area', 120)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('tanker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('owner_name', 120);
            $table->string('business_name', 150)->nullable();
            $table->string('alternate_mobile', 20)->nullable();
            $table->text('address')->nullable();
            $table->decimal('base_latitude', 10, 7)->nullable();
            $table->decimal('base_longitude', 10, 7)->nullable();
            $table->enum('verification_status', ['pending_review', 'approved', 'rejected', 'suspended', 'correction_requested'])->default('pending_review')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanker_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // optional own login
            $table->string('name', 120);
            $table->string('mobile', 20);
            $table->string('licence_number', 40)->nullable();
            $table->string('licence_document')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanker_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration_number', 20)->unique();
            $table->string('vehicle_type', 40)->default('water_tanker');
            $table->unsignedInteger('capacity_litres');
            $table->string('image_path')->nullable();
            $table->string('rc_path')->nullable();
            $table->json('service_areas')->nullable();
            $table->unsignedSmallInteger('service_radius_km')->nullable();
            $table->decimal('base_latitude', 10, 7)->nullable();
            $table->decimal('base_longitude', 10, 7)->nullable();
            $table->json('water_types')->nullable(); // null/empty = all types
            $table->enum('status', ['pending_review', 'approved', 'rejected', 'unavailable', 'active', 'suspended', 'correction_requested'])->default('pending_review')->index();
            $table->enum('availability', ['available', 'busy', 'unavailable'])->default('available')->index();
            $table->text('review_notes')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tanker_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('pricing_type', ['per_litre', 'per_trip']);
            $table->decimal('rate', 12, 4);
            $table->unsignedInteger('minimum_quantity')->nullable();
            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->string('partial_load_rule', 20)->default('full_trip'); // full_trip | pro_rata
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'superseded'])->default('pending')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('review_notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'pricing_type', 'approval_status']);
        });

        Schema::create('borewells', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('source_code', 30)->unique();
            $table->enum('source_type', ['borewell', 'bavi']);
            $table->text('address')->nullable();
            $table->string('area', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->json('water_types')->nullable();
            $table->decimal('internal_cost_per_litre', 10, 4)->nullable();
            $table->unsignedInteger('daily_capacity_litres')->nullable();
            $table->boolean('track_capacity')->default(false);
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('contact_mobile', 20)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['borewells', 'tanker_pricing', 'vehicles', 'driver_profiles', 'tanker_profiles', 'customer_addresses', 'customer_profiles', 'water_types', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
