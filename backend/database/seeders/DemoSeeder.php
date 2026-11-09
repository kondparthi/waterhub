<?php

namespace Database\Seeders;

use App\Models\Borewell;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample sources, a customer and two tanker operators for local/staging testing.
 *   php artisan db:seed --class=DemoSeeder
 * Never run on production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $sources = [
            ['Borewell 1', 'BW-01', 'borewell', 'Gachibowli', 17.4401, 78.3489, ['General Use Water', 'Borewell Water']],
            ['Borewell 2', 'BW-02', 'borewell', 'Kondapur', 17.4615, 78.3657, ['General Use Water', 'Borewell Water']],
            ['Borewell 3', 'BW-03', 'borewell', 'Madhapur', 17.4483, 78.3915, ['Drinking Water', 'General Use Water']],
            ['Bavi 1', 'BV-01', 'bavi', 'Nanakramguda', 17.4156, 78.3428, ['General Use Water']],
            ['Bavi 2', 'BV-02', 'bavi', 'Manikonda', 17.4040, 78.3860, ['General Use Water', 'Borewell Water']],
        ];
        foreach ($sources as [$name, $code, $type, $area, $lat, $lng, $types]) {
            Borewell::firstOrCreate(['source_code' => $code], [
                'name' => $name, 'source_type' => $type, 'area' => $area, 'address' => "$area, Hyderabad",
                'latitude' => $lat, 'longitude' => $lng, 'water_types' => $types, 'status' => 'active',
                'opening_time' => '05:00', 'closing_time' => '21:00',
            ]);
        }

        if (! User::where('mobile', '+919000000001')->exists()) {
            $c = User::create(['name' => 'Demo Customer', 'mobile' => '+919000000001', 'password' => 'Customer@123', 'role' => 'customer', 'status' => 'active']);
            $c->customerProfile()->create(['address' => 'Plot 12, Road 3, Kondapur', 'area' => 'Kondapur', 'latitude' => 17.4630, 'longitude' => 78.3570]);
            $c->addresses()->create(['label' => 'Home', 'address' => 'Plot 12, Road 3, Kondapur', 'area' => 'Kondapur', 'latitude' => 17.4630, 'longitude' => 78.3570, 'is_default' => true]);
        }

        $operators = [
            ['Ravi Kumar', '+919000000002', 'Ravi Water Supply', 17.4450, 78.3550, [['TS09AB1234', 5000, 0.50, 1200], ['TS09AB5678', 10000, 0.45, 2200]]],
            ['Suresh Reddy', '+919000000003', 'Sri Sai Tankers', 17.4300, 78.4000, [['TS10CD4321', 3000, 0.60, 900]]],
        ];
        foreach ($operators as [$name, $mobile, $biz, $lat, $lng, $vehicles]) {
            if (User::where('mobile', $mobile)->exists()) continue;
            $u = User::create(['name' => $name, 'mobile' => $mobile, 'password' => 'Tanker@123', 'role' => 'tanker', 'status' => 'active']);
            $p = $u->tankerProfile()->create(['owner_name' => $name, 'business_name' => $biz, 'address' => 'Hyderabad', 'base_latitude' => $lat, 'base_longitude' => $lng, 'verification_status' => 'approved', 'reviewed_at' => now()]);
            $d = $p->drivers()->create(['name' => $name, 'mobile' => $mobile, 'is_owner' => true, 'user_id' => $u->id]);
            foreach ($vehicles as [$reg, $cap, $perLitre, $perTrip]) {
                $v = $p->vehicles()->create(['registration_number' => $reg, 'capacity_litres' => $cap, 'driver_profile_id' => $d->id, 'status' => 'approved', 'availability' => 'available', 'base_latitude' => $lat, 'base_longitude' => $lng, 'service_radius_km' => 25]);
                $v->pricing()->create(['pricing_type' => 'per_litre', 'rate' => $perLitre, 'delivery_charge' => 200, 'effective_from' => now()->subMonth()->toDateString(), 'approval_status' => 'approved', 'approved_at' => now()]);
                $v->pricing()->create(['pricing_type' => 'per_trip', 'rate' => $perTrip, 'delivery_charge' => 200, 'effective_from' => now()->subMonth()->toDateString(), 'approval_status' => 'approved', 'approved_at' => now()]);
            }
        }
        $this->command?->info('Demo data: customer 9000000001 / Customer@123, tankers 9000000002 & 9000000003 / Tanker@123');
    }
}
