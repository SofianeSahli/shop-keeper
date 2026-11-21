<?php

namespace App\Modules\Contacts\Controllers;

use App\Modules\Contacts\Actions\CreateContact;
use App\Modules\Contacts\Actions\FindContactOfShop;
use App\Modules\Contacts\Actions\ListContacts;
use App\Modules\Contacts\Actions\UpdateContact;
use App\Modules\Contacts\Controllers\Requests\CreateContactRequest;
use App\Modules\Contacts\Controllers\Requests\ListContactsRequest;
use App\Modules\Contacts\Controllers\Requests\UpdateContactRequest;
use App\Modules\Contacts\Controllers\Resources\ContactResource;
use App\Shared\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ContactController
{
    public function index(ListContactsRequest $request, CurrentShop $currentShop, ListContacts $listContacts): AnonymousResourceCollection
    {
        $role = $request->role();
        $pageSize = $request->pageSize();
        $contacts = $listContacts->handle($currentShop->shopId, $role, $pageSize);

        return ContactResource::collection($contacts);
    }

    public function store(CreateContactRequest $request, CurrentShop $currentShop, CreateContact $createContact): JsonResponse
    {
        $newContact = $request->newContact($currentShop->shopId);
        $created = $createContact->handle($newContact);
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new ContactResource($created->record))->response()->setStatusCode($status);
    }

    public function show(string $contact, CurrentShop $currentShop, FindContactOfShop $findContactOfShop): ContactResource
    {
        $foundContact = $findContactOfShop->handle($currentShop->shopId, $contact);
        Gate::authorize('view', $foundContact);

        return new ContactResource($foundContact);
    }

    public function update(
        UpdateContactRequest $request,
        string $contact,
        CurrentShop $currentShop,
        FindContactOfShop $findContactOfShop,
        UpdateContact $updateContact,
    ): ContactResource {
        $foundContact = $findContactOfShop->handle($currentShop->shopId, $contact);
        Gate::authorize('update', $foundContact);

        $changes = $request->changes();
        $updatedContact = $updateContact->handle($foundContact, $changes);

        return new ContactResource($updatedContact);
    }
}
