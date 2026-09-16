<?php

namespace Database\Seeders;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        DB::transaction(function () {
            $user = User::updateOrCreate(['email' => 'demo@alquivo.test'], [
                'name' => 'Álex García', 'password' => 'demo12345', 'email_verified_at' => now(),
                'terms_accepted_at' => now(), 'terms_version' => '2026-07',
            ]);
            $portfolio = Portfolio::firstOrCreate(['name' => 'Patrimonio García'], ['currency' => 'EUR', 'country_code' => 'ES']);
            if (! $portfolio->billing_subscription_id && ! $portfolio->trial_ends_at) {
                $portfolio->update([
                    'plan' => 'free', 'storage_limit_bytes' => config('plans.free.storage_limit_bytes'),
                    'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(14),
                ]);
            }
            $portfolio->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            if ($portfolio->properties()->exists()) {
                return;
            }

            $property = $portfolio->properties()->create([
                'name' => 'Apartamento Ruzafa', 'type' => 'housing', 'address_line' => 'Carrer de Cadis, 42',
                'city' => 'Valencia', 'postal_code' => '46006', 'purchase_price' => 168000,
                'current_value' => 205000, 'outstanding_debt' => 92000, 'area' => 78,
                'bedrooms' => 2, 'bathrooms' => 1, 'purchase_date' => today()->subYears(3),
            ]);
            $second = $portfolio->properties()->create([
                'name' => 'Local Benimaclet', 'type' => 'commercial', 'address_line' => 'Carrer d\'Emili Baró, 18',
                'city' => 'Valencia', 'purchase_price' => 94000, 'current_value' => 112000, 'area' => 64,
            ]);
            $contact = Contact::create(['portfolio_id' => $portfolio->id, 'kind' => 'person', 'name' => 'Laura Martínez', 'email' => 'laura@example.com']);
            $lease = Lease::create([
                'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
                'start_date' => today()->subMonths(9), 'end_date' => today()->addMonths(3),
                'monthly_rent' => 950, 'deposit_amount' => 950, 'payment_day' => 5,
            ]);
            $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
            RentCharge::create([
                'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->format('Y-m'),
                'due_date' => today()->startOfMonth()->day(5), 'amount' => 950, 'paid_amount' => 950, 'status' => 'paid',
            ]);
            Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'lease_id' => $lease->id, 'direction' => 'income', 'category' => 'rent', 'description' => 'Alquiler del mes', 'amount' => 950, 'transaction_date' => today()->startOfMonth()->day(4), 'status' => 'paid']);
            Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'direction' => 'expense', 'category' => 'maintenance', 'description' => 'Revisión de caldera', 'amount' => 145, 'transaction_date' => today()->subDays(2), 'status' => 'paid']);
            Issue::create(['portfolio_id' => $portfolio->id, 'property_id' => $second->id, 'title' => 'Revisar persiana del escaparate', 'priority' => 'medium', 'status' => 'open', 'reported_at' => today()]);
            Reminder::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'title' => 'Revisar renovación del seguro', 'starts_at' => today()->addDays(12)->setTime(10, 0)]);
        });
    }
}
