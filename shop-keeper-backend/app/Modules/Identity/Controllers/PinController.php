<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\ChangePin;
use App\Modules\Identity\Actions\DisablePin;
use App\Modules\Identity\Actions\EnablePin;
use App\Modules\Identity\Actions\RecoverPin;
use App\Modules\Identity\Controllers\Requests\ChangePinRequest;
use App\Modules\Identity\Controllers\Requests\DisablePinRequest;
use App\Modules\Identity\Controllers\Requests\EnablePinRequest;
use App\Modules\Identity\Controllers\Requests\RecoverPinRequest;
use Illuminate\Http\Response;

final class PinController
{
    public function store(EnablePinRequest $request, EnablePin $enablePin): Response
    {
        $enablePin->handle($request->user(), $request->validated('pin'));

        return response()->noContent();
    }

    public function update(ChangePinRequest $request, ChangePin $changePin): Response
    {
        $changePin->handle($request->user(), $request->validated('current_pin'), $request->validated('pin'));

        return response()->noContent();
    }

    public function destroy(DisablePinRequest $request, DisablePin $disablePin): Response
    {
        $disablePin->handle($request->user(), $request->validated('current_pin'));

        return response()->noContent();
    }

    public function recover(RecoverPinRequest $request, RecoverPin $recoverPin): Response
    {
        $recoverPin->handle(
            $request->user(),
            $request->user()->currentAccessToken()->id,
            $request->validated('pin'),
            $request->validated('id_token'),
            $request->validated('password'),
        );

        return response()->noContent();
    }
}
