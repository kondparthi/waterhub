<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingViewController;
use App\Http\Controllers\Api\Customer;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Tanker;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('meta', [MetaController::class, 'index']);

    // ---------------- Auth (rate limited) ----------------
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register/customer', [AuthController::class, 'registerCustomer']);
        Route::post('register/tanker', [AuthController::class, 'registerTanker']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:otp');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:otp');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        // Booking detail & tracking: admin or the owning customer (policy-checked)
        Route::middleware('role:admin,customer')->group(function () {
            Route::get('bookings/{booking}', [BookingViewController::class, 'show']);
            Route::get('bookings/{booking}/tracking', [BookingViewController::class, 'tracking']);
        });

        // Private files (authorised per owner/admin)
        Route::get('files/vehicles/{vehicle}/{type}', [FileController::class, 'vehicle'])->whereIn('type', ['image', 'rc']);
        Route::get('files/drivers/{driver}/licence', [FileController::class, 'licence']);
        Route::get('files/assignments/{assignment}/proof', [FileController::class, 'proof']);

        // ---------------- Customer ----------------
        Route::middleware('role:customer')->group(function () {
            Route::get('customer/dashboard', [Customer\BookingController::class, 'dashboard']);
            Route::get('customer/profile', [Customer\ProfileController::class, 'show']);
            Route::put('customer/profile', [Customer\ProfileController::class, 'update']);
            Route::get('customer/addresses', [Customer\ProfileController::class, 'addresses']);
            Route::post('customer/addresses', [Customer\ProfileController::class, 'storeAddress']);
            Route::put('customer/addresses/{address}', [Customer\ProfileController::class, 'updateAddress']);
            Route::delete('customer/addresses/{address}', [Customer\ProfileController::class, 'destroyAddress']);

            Route::post('enquiries', [Customer\EnquiryController::class, 'store']);
            Route::get('my/enquiries', [Customer\EnquiryController::class, 'index']);
            Route::get('my/enquiries/{enquiry}', [Customer\EnquiryController::class, 'show']);
            Route::post('my/enquiries/{enquiry}/accept-quote', [Customer\EnquiryController::class, 'acceptQuote']);
            Route::post('my/enquiries/{enquiry}/cancel', [Customer\EnquiryController::class, 'cancel']);

            Route::post('bookings', [Customer\BookingController::class, 'store']);
            Route::get('my/bookings', [Customer\BookingController::class, 'index']);
            Route::post('my/bookings/{booking}/accept-quote', [Customer\BookingController::class, 'acceptQuote']);
            Route::post('my/bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel']);
        });

        // ---------------- Tanker owner / driver ----------------
        Route::middleware('role:tanker')->prefix('tanker')->group(function () {
            Route::get('dashboard', [Tanker\ProfileController::class, 'dashboard']);
            Route::get('profile', [Tanker\ProfileController::class, 'show']);
            Route::put('profile', [Tanker\ProfileController::class, 'update']);
            Route::get('drivers', [Tanker\ProfileController::class, 'drivers']);
            Route::post('drivers', [Tanker\ProfileController::class, 'storeDriver']);
            Route::post('drivers/{driver}', [Tanker\ProfileController::class, 'updateDriver']); // POST for multipart

            Route::get('vehicles', [Tanker\VehicleController::class, 'index']);
            Route::post('vehicles', [Tanker\VehicleController::class, 'store']);
            Route::get('vehicles/{vehicle}', [Tanker\VehicleController::class, 'show']);
            Route::post('vehicles/{vehicle}', [Tanker\VehicleController::class, 'update']); // POST for multipart
            Route::post('vehicles/{vehicle}/availability', [Tanker\VehicleController::class, 'availability']);
            Route::get('vehicles/{vehicle}/pricing', [Tanker\VehicleController::class, 'pricing']);
            Route::post('vehicles/{vehicle}/pricing', [Tanker\VehicleController::class, 'storePricing']);

            Route::get('leads', [Tanker\AssignmentController::class, 'leads']);
            Route::get('assignments', [Tanker\AssignmentController::class, 'index']);
            Route::get('assignments/{assignment}', [Tanker\AssignmentController::class, 'show']);
            Route::post('assignments/{assignment}/accept', [Tanker\AssignmentController::class, 'accept']);
            Route::post('assignments/{assignment}/reject', [Tanker\AssignmentController::class, 'reject']);
            Route::post('assignments/{assignment}/start', [Tanker\AssignmentController::class, 'start']);
            Route::post('assignments/{assignment}/arrive', [Tanker\AssignmentController::class, 'arrive']);
            Route::post('assignments/{assignment}/delivered', [Tanker\AssignmentController::class, 'delivered']);
            Route::post('locations', [Tanker\AssignmentController::class, 'location'])->middleware('throttle:gps');
        });

        // ---------------- Admin ----------------
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [Admin\DashboardController::class, 'index']);

            Route::get('customers', [Admin\CustomerController::class, 'index']);
            Route::get('customers/{customer}', [Admin\CustomerController::class, 'show']);
            Route::post('customers/{customer}/status', [Admin\CustomerController::class, 'updateStatus']);

            Route::get('tankers', [Admin\TankerController::class, 'index']);
            Route::get('tankers/{tanker}', [Admin\TankerController::class, 'show']);
            Route::post('tankers/{tanker}/review', [Admin\TankerController::class, 'review']);
            Route::post('tankers/{tanker}/approve', fn (\Illuminate\Http\Request $r, \App\Models\TankerProfile $tanker) => app(Admin\TankerController::class)->review($r->merge(['action' => 'approve']), $tanker));
            Route::get('vehicles', [Admin\TankerController::class, 'vehicles']);
            Route::get('vehicles/{vehicle}', [Admin\TankerController::class, 'vehicle']);
            Route::post('vehicles/{vehicle}/review', [Admin\TankerController::class, 'reviewVehicle']);
            Route::post('vehicles/{vehicle}/pricing', [Admin\TankerController::class, 'storePricing']);
            Route::get('pricing', [Admin\TankerController::class, 'pricing']);
            Route::post('pricing/{pricing}/review', [Admin\TankerController::class, 'reviewPricing']);

            Route::get('borewells', [Admin\BorewellController::class, 'index']);
            Route::post('borewells', [Admin\BorewellController::class, 'store']);
            Route::get('borewells/{borewell}', [Admin\BorewellController::class, 'show']);
            Route::put('borewells/{borewell}', [Admin\BorewellController::class, 'update']);
            Route::delete('borewells/{borewell}', [Admin\BorewellController::class, 'destroy']);
            Route::get('water-types', [Admin\BorewellController::class, 'waterTypes']);
            Route::post('water-types', [Admin\BorewellController::class, 'storeWaterType']);
            Route::put('water-types/{waterType}', [Admin\BorewellController::class, 'updateWaterType']);

            Route::get('enquiries', [Admin\EnquiryController::class, 'index']);
            Route::get('enquiries/{enquiry}', [Admin\EnquiryController::class, 'show']);
            Route::post('enquiries/{enquiry}/notes', [Admin\EnquiryController::class, 'note']);
            Route::post('enquiries/{enquiry}/quote', [Admin\EnquiryController::class, 'quote']);
            Route::post('enquiries/{enquiry}/convert', [Admin\EnquiryController::class, 'convert']);
            Route::post('enquiries/{enquiry}/close', [Admin\EnquiryController::class, 'close']);

            Route::get('bookings', [Admin\BookingController::class, 'index']);
            Route::post('bookings/{booking}/review', [Admin\BookingController::class, 'review']);
            Route::get('bookings/{booking}/sources', [Admin\BookingController::class, 'sources']);
            Route::post('bookings/{booking}/source', [Admin\BookingController::class, 'selectSource']);
            Route::post('bookings/{booking}/quote', [Admin\BookingController::class, 'quote']);
            Route::post('bookings/{booking}/confirm', [Admin\BookingController::class, 'confirm']);
            Route::get('bookings/{booking}/eligible-tankers', [Admin\BookingController::class, 'eligibleTankers']);
            Route::post('bookings/{booking}/assign', [Admin\BookingController::class, 'assign']);
            Route::post('bookings/{booking}/cancel', [Admin\BookingController::class, 'cancel']);
            Route::post('bookings/{booking}/reject', [Admin\BookingController::class, 'reject']);
            Route::post('bookings/{booking}/complete', [Admin\BookingController::class, 'complete']);

            Route::get('payments', [Admin\PaymentController::class, 'index']);
            Route::post('payments', [Admin\PaymentController::class, 'store']);
            Route::post('payments/{payment}/void', [Admin\PaymentController::class, 'void']);

            Route::get('tracking/active', [Admin\TrackingController::class, 'active']);
            Route::get('reports/summary', [Admin\ReportController::class, 'summary']);

            Route::get('settings', [Admin\SettingController::class, 'index']);
            Route::put('settings', [Admin\SettingController::class, 'update']);
            Route::get('admins', [Admin\SettingController::class, 'admins']);
            Route::post('admins', [Admin\SettingController::class, 'storeAdmin']);
            Route::put('admins/{admin}', [Admin\SettingController::class, 'updateAdmin']);
            Route::get('audit-logs', [Admin\SettingController::class, 'auditLogs']);
        });
    });
});
