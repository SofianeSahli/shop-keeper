<?php

namespace App\Modules\Contacts;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Policies\ContactPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContactsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(Contact::class, ContactPolicy::class);
    }
}
