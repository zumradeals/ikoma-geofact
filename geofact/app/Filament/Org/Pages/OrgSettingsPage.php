<?php

namespace App\Filament\Org\Pages;

use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OrgSettingsPage extends Page
{

    protected static ?string $title = 'Paramètres organisation';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.org.pages.org-settings';

    public static function getNavigationIcon(): string { return 'heroicon-o-cog-6-tooth'; }
    public static function getNavigationGroup(): ?string { return null; }

    public ?array $data = [];

    public function mount(): void
    {
        $org = Organization::find(Auth::user()?->organization_id);
        $this->form->fill([
            'name'     => $org?->name,
            'timezone' => $org?->timezone,
            'country_code' => $org?->country_code,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nom de l\'organisation')
                    ->required()
                    ->maxLength(150),

                Select::make('timezone')
                    ->label('Fuseau horaire')
                    ->options([
                        'Africa/Abidjan'      => 'Abidjan (UTC+0)',
                        'Africa/Dakar'        => 'Dakar (UTC+0)',
                        'Africa/Bamako'       => 'Bamako (UTC+0)',
                        'Africa/Ouagadougou'  => 'Ouagadougou (UTC+0)',
                        'Africa/Conakry'      => 'Conakry (UTC+0)',
                        'Africa/Lome'         => 'Lomé (UTC+0)',
                        'Africa/Porto-Novo'   => 'Porto-Novo (UTC+1)',
                        'Africa/Lagos'        => 'Lagos (UTC+1)',
                        'Africa/Douala'       => 'Douala (UTC+1)',
                        'Africa/Accra'        => 'Accra (UTC+0)',
                    ])
                    ->required()
                    ->searchable(),

                Select::make('country_code')
                    ->label('Pays')
                    ->options([
                        'CI' => 'Côte d\'Ivoire',
                        'SN' => 'Sénégal',
                        'ML' => 'Mali',
                        'BF' => 'Burkina Faso',
                        'GN' => 'Guinée',
                        'TG' => 'Togo',
                        'BJ' => 'Bénin',
                        'CM' => 'Cameroun',
                        'GH' => 'Ghana',
                        'NG' => 'Nigéria',
                    ])
                    ->required()
                    ->searchable(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $org  = Organization::find(Auth::user()?->organization_id);

        if ($org) {
            $org->update([
                'name'         => $data['name'],
                'timezone'     => $data['timezone'],
                'country_code' => $data['country_code'],
            ]);
            Notification::make()->title('Paramètres sauvegardés')->success()->send();
        }
    }
}
