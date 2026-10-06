<?php

namespace App\View\Composers;

use App\Models\Notification;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardLayoutComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        $isSuperAdmin = $view->getName() === 'super-admin.layout.master'
            || ($user instanceof User && $user->isSuperAdmin());
        $impersonatedRestaurant = $isSuperAdmin ? Tenancy::impersonatedRestaurant() : null;
        $showManagerNav = !$isSuperAdmin || ($impersonatedRestaurant && request()->is('manager/*'));
        $isManagerPanel = $showManagerNav && request()->is('manager/*');
        $restaurant = $showManagerNav
            ? ($impersonatedRestaurant ?? ($user instanceof User ? $user->restaurant : null))
            : null;

        $platformName = PlatformSetting::getValue('platform_name', 'CodeIbex');
        $platformTagline = PlatformSetting::getValue('platform_tagline', 'Business platform');
        $platformLogo = PlatformSetting::getValue('platform_logo_path', '');
        $platformLight = PlatformSetting::getValue('platform_theme_light', '#E7F0FA');
        $platformAccent = PlatformSetting::getValue('platform_theme_accent', '#7BA4D0');
        $platformPrimary = PlatformSetting::getValue('platform_theme_primary', '#2E5E99');
        $platformDark = PlatformSetting::getValue('platform_theme_dark', '#0D2440');

        $dashboardStyle = $restaurant
            ? $restaurant->themeCssVariables()
            : implode('; ', [
                '--tenant-cream: ' . $platformLight,
                '--tenant-accent: ' . $platformAccent,
                '--tenant-accent-dark: ' . $platformPrimary,
                '--tenant-primary: ' . $platformPrimary,
                '--tenant-primary-light: ' . $platformAccent,
                '--tenant-dark: ' . $platformDark,
            ]);
        $managerTheme = $restaurant?->restaurantTheme;
        $customerTheme = $managerTheme?->customerTheme();
        $dashboardLightPalette = $managerTheme?->managerPalette('light') ?? [
            'background' => $platformLight,
            'surface' => $platformLight,
            'accent' => $platformAccent,
            'primary' => $platformPrimary,
            'dark' => $platformDark,
        ];
        $dashboardDarkPalette = $managerTheme?->managerPalette('dark') ?? [
            'background' => '#0F172A',
            'surface' => '#0F172A',
            'accent' => '#93C5FD',
            'primary' => '#1D4ED8',
            'dark' => '#0B1220',
        ];

        $recentNotifications = new Collection();
        $unreadNotificationCount = 0;
        if ($user instanceof User && ($restaurant || $isSuperAdmin)) {
            try {
                $recentNotifications = Notification::query()->latest()->limit(5)->get();
                $unreadNotificationCount = Notification::query()->whereNull('read_at')->count();
            } catch (\Throwable $exception) {
                Log::warning('Dashboard notification query failed; rendering without tenant notifications.', [
                    'restaurant_id' => $restaurant?->id ?? $impersonatedRestaurant?->id ?? null,
                    'user_id' => $user->id,
                    'is_super_admin' => $isSuperAdmin,
                    'error' => $exception->getMessage(),
                ]);
                $recentNotifications = new Collection();
                $unreadNotificationCount = 0;
            }
        }

        $view->with(compact(
            'user',
            'isSuperAdmin',
            'impersonatedRestaurant',
            'showManagerNav',
            'isManagerPanel',
            'restaurant',
            'platformName',
            'platformTagline',
            'platformLogo',
            'dashboardStyle',
            'managerTheme',
            'customerTheme',
            'dashboardLightPalette',
            'dashboardDarkPalette',
            'recentNotifications',
            'unreadNotificationCount'
        ));
        $view->with([
            'dashboardLight' => $dashboardLightPalette['surface'],
            'dashboardAccent' => $dashboardLightPalette['accent'],
            'dashboardPrimary' => $dashboardLightPalette['primary'],
            'dashboardDark' => $dashboardLightPalette['dark'],
            'navPrefix' => $showManagerNav ? 'manager' : 'admin',
            'logoutRoute' => $isSuperAdmin ? 'admin.logout' : 'manager.logout',
            'moduleEnabled' => fn (string $key): bool => $user instanceof User && $user->hasModuleAccess($key),
        ]);
    }
}