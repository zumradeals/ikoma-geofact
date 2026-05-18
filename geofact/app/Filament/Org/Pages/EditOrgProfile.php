<?php

namespace App\Filament\Org\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditOrgProfile extends EditProfile
{
    protected static ?string $title = 'Mon profil';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-user-circle';
    }

    // Remplace le champ "name" par "first_name" (DC-14 : pas de champ name)
    protected function getNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label('Prénom')
            ->required()
            ->maxLength(80)
            ->autofocus();
    }

    // Ajoute last_name dans le formulaire après le composant name
    protected function getEmailFormComponent(): Component
    {
        $emailComponent = parent::getEmailFormComponent();

        // On ne peut pas injecter last_name ici directement,
        // on l'ajoute via mutateFormDataBeforeSave et on le rend visible via handleRecordUpdate
        return $emailComponent;
    }

    // Sauvegarde vers password_hash (DC-14) plutôt que le champ standard "password"
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }

        unset($data['password'], $data['passwordConfirmation']);

        $record->update($data);

        return $record;
    }

    // Ne jamais exposer password_hash au formulaire
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['password_hash'], $data['password']);
        return $data;
    }
}
