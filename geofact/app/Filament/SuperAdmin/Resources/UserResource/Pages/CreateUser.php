<?php
namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;
use App\Filament\SuperAdmin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreateUser extends CreateRecord {
    protected static string $resource = UserResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array { $data["id"] = Str::uuid()->toString(); $data["created_by"] = auth()->id() ?? Str::uuid()->toString(); $data["token_version"] = 1; $data["created_at"] = now(); return $data; }
}
