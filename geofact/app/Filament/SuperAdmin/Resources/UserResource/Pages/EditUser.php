<?php
namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;
use App\Filament\SuperAdmin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
class EditUser extends EditRecord {
    protected static string $resource = UserResource::class;
    
    
}
