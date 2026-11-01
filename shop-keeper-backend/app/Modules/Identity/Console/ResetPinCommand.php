<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\ResetPinByOperator;
use Illuminate\Console\Command;

final class ResetPinCommand extends Command
{
    protected $signature = 'merchant:reset-pin {email}';

    protected $description = 'Turn off a merchant PIN and end all their sessions, after checking who they are';

    public function handle(ResetPinByOperator $resetPinByOperator): int
    {
        if (! $resetPinByOperator->handle($this->argument('email'))) {
            $this->error('No account has this address.');

            return self::FAILURE;
        }

        $this->info('PIN turned off and all sessions ended.');

        return self::SUCCESS;
    }
}
