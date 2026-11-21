<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;
use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use App\Shared\Records\CancelOnce;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const CREATE_TEMPORARY_RECORDS = 'CREATE TEMPORARY TABLE cancel_once_records (
    id BINARY(16) PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL
)';

const CREATE_TEMPORARY_CANCELLATIONS = 'CREATE TEMPORARY TABLE cancel_once_cancellations (
    id BINARY(16) PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    record_id BINARY(16) NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,
    cancelled_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL
)';

#[Fillable(['id', 'shop_id'])]
class CancelOnceRecord extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected $table = 'cancel_once_records';
}

#[Fillable(['id', 'shop_id', 'record_id', 'reason', 'cancelled_by'])]
class CancelOnceCancellation extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected $table = 'cancel_once_cancellations';

    protected function casts(): array
    {
        return ['record_id' => BinaryUuid::class];
    }
}

function cancellationOf(CancelOnceRecord $record, string $cancellationId, int $accountId): CancelOnceCancellation
{
    $recordId = $record->uuid();

    return new CancelOnceCancellation([
        'id' => $cancellationId,
        'shop_id' => $record->shop_id,
        'record_id' => $recordId,
        'reason' => 'wrong record',
        'cancelled_by' => $accountId,
    ]);
}

beforeEach(function () {
    DB::statement(CREATE_TEMPORARY_RECORDS);
    DB::statement(CREATE_TEMPORARY_CANCELLATIONS);

    $this->account = User::factory()->create();
    $shop = Shop::factory()->create(['owner_id' => $this->account->id]);
    $recordId = (string) Str::uuid7();
    $otherRecordId = (string) Str::uuid7();
    $this->record = CancelOnceRecord::query()->create(['id' => $recordId, 'shop_id' => $shop->id]);
    $this->otherRecord = CancelOnceRecord::query()->create(['id' => $otherRecordId, 'shop_id' => $shop->id]);
    $this->cancelOnce = app(CancelOnce::class);
    $this->extraSteps = 0;
    $this->afterCancelling = function (Model $locked): void {
        $this->extraSteps++;
    };
});

afterEach(function () {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS cancel_once_cancellations, cancel_once_records');
});

it('records the cancellation with its reason and account, then runs the extra step', function () {
    $cancellationId = (string) Str::uuid7();
    $cancellation = cancellationOf($this->record, $cancellationId, $this->account->id);

    $created = $this->cancelOnce->handle($this->record, $cancellation, 'record_id', $this->afterCancelling);

    $answeredId = $created->record->uuid();
    $recordId = $this->record->uuid();
    $stored = CancelOnceCancellation::query()->sole();
    $storedId = $stored->uuid();
    expect($created->wasCreated)->toBeTrue();
    expect($answeredId)->toBe($recordId);
    expect($this->extraSteps)->toBe(1);
    expect([$storedId, $stored->record_id, $stored->reason, $stored->cancelled_by])
        ->toBe([$cancellationId, $recordId, 'wrong record', $this->account->id]);
});

it('answers the record unchanged when it is already cancelled', function () {
    $firstId = (string) Str::uuid7();
    $secondId = (string) Str::uuid7();
    $first = cancellationOf($this->record, $firstId, $this->account->id);
    $second = cancellationOf($this->record, $secondId, $this->account->id);
    $this->cancelOnce->handle($this->record, $first, 'record_id', $this->afterCancelling);

    $created = $this->cancelOnce->handle($this->record, $second, 'record_id', $this->afterCancelling);

    $cancellationCount = CancelOnceCancellation::query()->count();
    expect($created->wasCreated)->toBeFalse();
    expect($this->extraSteps)->toBe(1);
    expect($cancellationCount)->toBe(1);
});

it('refuses a cancellation id already used by another cancellation', function () {
    $cancellationId = (string) Str::uuid7();
    $first = cancellationOf($this->otherRecord, $cancellationId, $this->account->id);
    $reused = cancellationOf($this->record, $cancellationId, $this->account->id);
    $this->cancelOnce->handle($this->otherRecord, $first, 'record_id', $this->afterCancelling);

    $cancel = fn () => $this->cancelOnce->handle($this->record, $reused, 'record_id', $this->afterCancelling);

    expect($cancel)->toThrow(function (ValidationException $refusal) {
        $errors = $refusal->errors();
        expect($errors)->toBe(['id' => ['id.taken']]);
    });
    expect($this->extraSteps)->toBe(1);
});

it('records nothing when the extra step refuses the cancellation', function () {
    $cancellationId = (string) Str::uuid7();
    $cancellation = cancellationOf($this->record, $cancellationId, $this->account->id);
    $refusingStep = function (Model $locked): void {
        throw new RuntimeException('refused');
    };

    $cancel = fn () => $this->cancelOnce->handle($this->record, $cancellation, 'record_id', $refusingStep);

    expect($cancel)->toThrow(RuntimeException::class);

    $cancellationCount = CancelOnceCancellation::query()->count();
    expect($cancellationCount)->toBe(0);
});
