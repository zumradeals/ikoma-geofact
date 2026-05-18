<?php

namespace App\Filament\Org\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryPoliciesPage extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $title = 'Politiques de notification';
    protected static ?int $navigationSort = 11;
    protected string $view = 'filament.org.pages.delivery-policies';

    public static function getNavigationIcon(): string { return 'heroicon-o-bell-alert'; }
    public static function getNavigationGroup(): ?string { return null; }

    public function table(Table $table): Table
    {
        $orgId = Auth::user()?->organization_id;

        return $table
            ->query(fn (): Builder => \App\Models\DeliveryPolicy::query()
                ->where('organization_id', $orgId)
                ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('event_type_filter')
                    ->label('Événement')
                    ->badge()
                    ->default('*'),

                Tables\Columns\TextColumn::make('min_severity')
                    ->label('Sévérité min.')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'CRITICAL' => 'danger',
                        'HIGH'     => 'warning',
                        'MEDIUM'   => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('channels')
                    ->label('Canaux')
                    ->formatStateUsing(fn (?string $state) => $state ? implode(', ', json_decode($state, true) ?? []) : '—'),

                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Email destinataire')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('recipient_phone')
                    ->label('WhatsApp')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Ajouter une politique')
                    ->icon('heroicon-o-plus')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('event_type_filter')
                            ->label('Filtre événement (ex: alert.overspeed.* ou laissez vide pour tout)')
                            ->placeholder('alert.*'),

                        \Filament\Forms\Components\Select::make('min_severity')
                            ->label('Sévérité minimale')
                            ->options(['LOW' => 'LOW', 'MEDIUM' => 'MEDIUM', 'HIGH' => 'HIGH', 'CRITICAL' => 'CRITICAL'])
                            ->default('MEDIUM'),

                        \Filament\Forms\Components\CheckboxList::make('channels')
                            ->label('Canaux')
                            ->options(['email' => 'Email', 'whatsapp' => 'WhatsApp', 'api' => 'Webhook API'])
                            ->default(['email'])
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('recipient_email')
                            ->label('Email destinataire')
                            ->email(),

                        \Filament\Forms\Components\TextInput::make('recipient_phone')
                            ->label('Téléphone WhatsApp (ex: +2250700000000)')
                            ->tel(),

                        \Filament\Forms\Components\TextInput::make('recipient_webhook_url')
                            ->label('URL Webhook')
                            ->url(),
                    ])
                    ->action(function (array $data) {
                        $orgId = Auth::user()?->organization_id;
                        DB::table('delivery_policies')->insert([
                            'id'                    => Str::uuid()->toString(),
                            'organization_id'       => $orgId,
                            'event_type_filter'     => $data['event_type_filter'] ?: null,
                            'min_severity'          => $data['min_severity'],
                            'channels'              => json_encode($data['channels']),
                            'recipient_email'       => $data['recipient_email'] ?: null,
                            'recipient_phone'       => $data['recipient_phone'] ?: null,
                            'recipient_webhook_url' => $data['recipient_webhook_url'] ?: null,
                            'status'                => 'active',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ]);
                        Notification::make()->title('Politique créée')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('toggle')
                    ->label(fn ($record) => $record->status === 'active' ? 'Désactiver' : 'Activer')
                    ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->status === 'active' ? 'warning' : 'success')
                    ->action(function ($record) {
                        DB::table('delivery_policies')
                            ->where('id', $record->id)
                            ->update([
                                'status'     => $record->status === 'active' ? 'inactive' : 'active',
                                'updated_at' => now(),
                            ]);
                        Notification::make()->title('Statut mis à jour')->success()->send();
                    }),

                Action::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        DB::table('delivery_policies')->where('id', $record->id)->delete();
                        Notification::make()->title('Politique supprimée')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
