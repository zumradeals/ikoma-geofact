<?php

namespace App\Providers\Filament;

use App\Models\Organization;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OrgPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('org')
            ->path('app')
            ->login()
            ->tenant(Organization::class, slugAttribute: 'id')
            ->colors([
                'primary'   => Color::hex('#1e3a5f'),
                'secondary' => Color::hex('#f97316'),
            ])
            ->brandName('IKOMA GEOFACT')
            ->discoverResources(in: app_path('Filament/Org/Resources'), for: 'App\Filament\Org\Resources')
            ->discoverPages(in: app_path('Filament/Org/Pages'), for: 'App\Filament\Org\Pages')
            ->discoverWidgets(in: app_path('Filament/Org/Widgets'), for: 'App\Filament\Org\Widgets')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
