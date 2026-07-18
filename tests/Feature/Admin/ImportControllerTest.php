<?php

namespace Tests\Feature\Admin;

use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * Build a real CSV UploadedFile on the fake disk's temp area.
     */
    private function csvUpload(string $contents, string $name = 'data.csv'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($tmp, $contents);

        return new UploadedFile($tmp, $name, 'text/csv', null, true);
    }

    public function test_non_admin_cannot_access_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('tools.import.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_import_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('tools.import.index'))
            ->assertOk();
    }

    public function test_unknown_type_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('tools.import.upload', ['type' => 'banana']))
            ->assertNotFound();
    }

    public function test_happy_path_expense_upload_preview_commit(): void
    {
        Storage::fake('local');

        $cat = ExpenseCategory::factory()->create(['name' => 'Office']);
        $method = PaymentMethod::factory()->create(['name' => 'Cash']);
        $admin = $this->admin();

        $csv = "Date,Payee,Category,Method,Amount\n"
            ."2026-05-01,Acme Ltd,Office,Cash,123.45\n"
            ."2026-05-02,Beta Co,Office,Cash,67.89\n";

        // Step 1: upload -> mapping screen
        $upload = $this->actingAs($admin)->post(
            route('tools.import.handle_upload', ['type' => 'expenses']),
            ['file' => $this->csvUpload($csv), 'has_header' => '1']
        );
        $upload->assertOk();

        // Step 2: preview with explicit column mapping (PhpSpreadsheet keys: A,B,C,...)
        $mapping = [
            'expense_date' => 'A',
            'payee_name' => 'B',
            'expense_category' => 'C',
            'payment_method' => 'D',
            'amount' => 'E',
        ];
        $preview = $this->actingAs($admin)->post(
            route('tools.import.preview', ['type' => 'expenses']),
            ['map' => $mapping]
        );
        $preview->assertOk();

        // Step 3: commit
        $commit = $this->actingAs($admin)->post(route('tools.import.commit', ['type' => 'expenses']));
        $commit->assertRedirect(route('tools.import.index'));
        $commit->assertSessionHas('success');

        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('expenses', [
            'payee_name' => 'Acme Ltd',
            'amount' => '123.45',
            'expense_category_id' => $cat->id,
            'payment_method_id' => $method->id,
            'created_by' => $admin->id,
        ]);
    }

    public function test_happy_path_income_upload_preview_commit(): void
    {
        Storage::fake('local');

        // Income is "wide": each source is a column header.
        IncomeSource::factory()->create(['name' => 'Cash']);
        IncomeSource::factory()->create(['name' => 'Visa']);
        $admin = $this->admin();

        $csv = "Date,Cash,Visa\n"
            ."2026-05-01,100,200\n"
            ."2026-05-02,0,50\n"
            ."2026-05-03,,75\n";

        $this->actingAs($admin)->post(
            route('tools.import.handle_upload', ['type' => 'income']),
            ['file' => $this->csvUpload($csv), 'has_header' => '1']
        )->assertOk();

        $this->actingAs($admin)->post(
            route('tools.import.preview', ['type' => 'income']),
            ['date_col' => 'A', 'source_cols' => ['B', 'C'], 'year' => 2026]
        )->assertOk();

        $this->actingAs($admin)->post(route('tools.import.commit', ['type' => 'income']))
            ->assertRedirect(route('tools.import.index'));

        // An explicit 0 is a real entry; only the blank cell is skipped.
        // 100 + 200 + 0 + 50 + 75 = 5 rows, and no row for 2026-05-03 Cash.
        $this->assertDatabaseCount('incomes', 5);

        $this->assertDatabaseHas('incomes', [
            'income_date' => '2026-05-02',
            'amount' => '0.00',
        ]);

        $this->assertDatabaseMissing('incomes', [
            'income_date' => '2026-05-03',
            'income_source_id' => IncomeSource::where('name', 'Cash')->value('id'),
        ]);
    }

    public function test_income_reimport_of_same_month_updates_instead_of_failing(): void
    {
        Storage::fake('local');

        IncomeSource::factory()->create(['name' => 'Cash']);
        IncomeSource::factory()->create(['name' => 'Visa']);
        $admin = $this->admin();

        $import = function (string $csv) use ($admin) {
            $this->actingAs($admin)->post(
                route('tools.import.handle_upload', ['type' => 'income']),
                ['file' => $this->csvUpload($csv), 'has_header' => '1']
            )->assertOk();

            $this->actingAs($admin)->post(
                route('tools.import.preview', ['type' => 'income']),
                ['date_col' => 'A', 'source_cols' => ['B', 'C'], 'year' => 2026]
            )->assertOk();

            $this->actingAs($admin)->post(route('tools.import.commit', ['type' => 'income']))
                ->assertRedirect(route('tools.import.index'));
        };

        $import("Date,Cash,Visa\n2026-05-01,100,200\n");
        $import("Date,Cash,Visa\n2026-05-01,150,200\n");

        // Same (date, source) pairs: updated in place, not duplicated.
        $this->assertDatabaseCount('incomes', 2);
        $this->assertDatabaseHas('incomes', [
            'income_date' => '2026-05-01',
            'income_source_id' => IncomeSource::where('name', 'Cash')->value('id'),
            'amount' => '150.00',
        ]);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('local');

        // 11 MB exceeds the 10 MB (10240 KB) cap.
        $big = UploadedFile::fake()->create('huge.csv', 11 * 1024, 'text/csv');

        $this->actingAs($this->admin())
            ->from(route('tools.import.upload', ['type' => 'expenses']))
            ->post(route('tools.import.handle_upload', ['type' => 'expenses']), ['file' => $big])
            ->assertSessionHasErrors('file');
    }

    public function test_upload_rejects_too_many_rows(): void
    {
        Storage::fake('local');

        $admin = $this->admin();

        // 5001 data rows + header exceeds MAX_IMPORT_ROWS (5000).
        $lines = "Date,Payee,Category,Method,Amount\n";
        for ($i = 1; $i <= 5001; $i++) {
            $lines .= "2026-05-01,Payee {$i},Office,Cash,10\n";
        }

        // Upload succeeds (size ok); the row cap is enforced at preview (readAllRows).
        $this->actingAs($admin)->post(
            route('tools.import.handle_upload', ['type' => 'expenses']),
            ['file' => $this->csvUpload($lines), 'has_header' => '1']
        )->assertOk();

        $mapping = [
            'expense_date' => 'A',
            'payee_name' => 'B',
            'expense_category' => 'C',
            'payment_method' => 'D',
            'amount' => 'E',
        ];

        $this->actingAs($admin)->post(
            route('tools.import.preview', ['type' => 'expenses']),
            ['map' => $mapping]
        )->assertStatus(422);
    }
}
