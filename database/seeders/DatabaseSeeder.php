<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ContractorJob;
use App\Models\CustomerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobPayment;
use App\Models\Party;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'admin@shatbha.test')->exists()) {
            return;
        }

        $company = Company::query()->create([
            'name' => 'شطبة',
            'subtitle' => 'أتيليه التشطيبات والمقاولات',
            'pack' => 'finishing',
        ]);

        User::query()->create([
            'company_id' => $company->id,
            'name' => 'مدير',
            'email' => 'admin@shatbha.test',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        User::query()->create([
            'company_id' => $company->id,
            'name' => 'كاتب',
            'email' => 'clerk@shatbha.test',
            'role' => 'clerk',
            'password' => Hash::make('password'),
        ]);

        $khaled = Party::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'خالد',
            'phone' => '01000000001',
            'kind' => 'agreement',
            'opening_balance' => 0,
            'agreement_estimate' => 50000,
            'supervision_percent' => 0,
        ]);
        $bedir = Party::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'بدير',
            'phone' => '01000000002',
            'kind' => 'supervision',
            'opening_balance' => 0,
            'supervision_percent' => 8,
        ]);

        $contractor = Party::query()->create([
            'company_id' => $company->id,
            'type' => 'contractor',
            'name' => 'أحمد',
            'kind' => 'agreement',
        ]);

        foreach (['تأسيس سباكة', 'تأسيس نقاشة', 'أرضيات'] as $name) {
            WorkType::query()->create(['company_id' => $company->id, 'name' => $name]);
        }

        $subs = ExpenseCategory::query()->create(['company_id' => $company->id, 'name' => 'اشتراكات وفواتير']);
        $tips = ExpenseCategory::query()->create(['company_id' => $company->id, 'name' => 'إكراميات وبدلات']);

        CustomerEntry::query()->create([
            'company_id' => $company->id,
            'customer_id' => $khaled->id,
            'entry_date' => '2026-05-30',
            'entry_type' => 'cash',
            'title' => 'دفعة تعاقد',
            'amount' => 10000,
        ]);
        CustomerEntry::query()->create([
            'company_id' => $company->id,
            'customer_id' => $bedir->id,
            'entry_date' => '2024-05-15',
            'entry_type' => 'cash',
            'title' => 'تحصيل نسب إشراف',
            'amount' => 1000,
        ]);
        CustomerEntry::query()->create([
            'company_id' => $company->id,
            'customer_id' => $khaled->id,
            'entry_date' => '2026-05-30',
            'entry_type' => 'labor',
            'title' => 'تأسيس سباكة',
            'labor_amount' => 10000,
            'return_amount' => 2000,
        ]);
        CustomerEntry::query()->create([
            'company_id' => $company->id,
            'customer_id' => $khaled->id,
            'entry_date' => '2026-05-31',
            'entry_type' => 'labor',
            'title' => 'تأسيس نقاشة',
            'labor_amount' => 5000,
        ]);

        // P&L fixture: supervision cash 1,000 − office 100 = net 900.
        Expense::query()->create([
            'company_id' => $company->id,
            'category_id' => $subs->id,
            'entry_date' => '2024-05-15',
            'title' => 'مصروف مكتبي',
            'amount' => 100,
        ]);
        Expense::query()->create([
            'company_id' => $company->id,
            'category_id' => $tips->id,
            'entry_date' => '2026-06-27',
            'title' => 'إكرامية موقع',
            'amount' => 1000,
        ]);

        $job = ContractorJob::query()->create([
            'company_id' => $company->id,
            'contractor_id' => $contractor->id,
            'title' => 'محارة فيلا',
            'qty' => 100,
            'unit_price' => 200,
        ]);
        JobPayment::query()->create([
            'job_id' => $job->id,
            'sequence' => 1,
            'amount' => 13000,
            'paid_on' => '2026-04-01',
        ]);
        // total 20,000 − 13,000 = 7,000 remaining

        $this->call(EcosystemSeeder::class);
    }
}
