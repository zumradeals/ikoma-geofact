<?php

namespace App\Filament\SuperAdmin\Resources\ConnectorResource\Pages;

use App\Filament\SuperAdmin\Resources\ConnectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewConnector extends ViewRecord
{
    protected static string $resource = ConnectorResource::class;

    protected string $view = 'filament.superadmin.resources.connector-resource.pages.view-connector';

    public ?string $revealedToken = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $key = 'connector_token_show_' . $this->record->id;

        if (session()->has($key)) {
            $this->revealedToken = session()->pull($key);

            \Filament\Notifications\Notification::make()
                ->title('Token connecteur — copiez-le maintenant')
                ->body('Il ne sera plus affiché après fermeture de cette page. Token : ' . $this->revealedToken)
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
