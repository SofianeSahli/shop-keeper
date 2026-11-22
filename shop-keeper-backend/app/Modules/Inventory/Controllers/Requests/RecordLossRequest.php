<?php

namespace App\Modules\Inventory\Controllers\Requests;

use App\Modules\Inventory\Models\LossReason;
use App\Modules\Inventory\Models\StockAdjustmentKind;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Support\NewStockAdjustmentLine;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class RecordLossRequest extends StockAdjustmentRequest
{
    protected function kind(): StockAdjustmentKind
    {
        return StockAdjustmentKind::Loss;
    }

    protected function lineRules(): array
    {
        $quantityRules = $this->quantityRules();
        $lossReason = Rule::enum(LossReason::class);

        return [
            ...$quantityRules,
            'lines.*.reason' => ['required', $lossReason],
            'lines.*.note' => ['nullable', 'string', 'max:'.StockAdjustmentLine::NOTE_MAX_LENGTH],
        ];
    }

    protected function lineMessages(): array
    {
        $quantityMessages = $this->quantityMessages();

        return [
            ...$quantityMessages,
            'lines.*.reason.required' => 'reason.required',
            'lines.*.reason.'.Enum::class => 'reason.invalid',
            'lines.*.note.string' => 'note.invalid',
            'lines.*.note.max' => 'note.too_long',
        ];
    }

    protected function newLine(string $lineId, string $productId, array $validatedLine): NewStockAdjustmentLine
    {
        $lossReason = LossReason::from($validatedLine['reason']);
        $note = $validatedLine['note'] ?? null;

        return new NewStockAdjustmentLine(
            $lineId,
            $productId,
            quantity: $validatedLine['quantity'],
            lossReason: $lossReason,
            note: $note,
        );
    }
}
