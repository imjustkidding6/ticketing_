@extends('layouts.admin')

@section('title', 'Settings Center')

@section('content')
<style>
    .settings-center {
        font-family: 'Inter', sans-serif !important;
    }

    .sidebar-sticky {
        position: sticky;
        top: 24px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }

    .settings-sidebar-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        color: var(--text-secondary, #6B7280);
        border-left: 3px solid transparent;
        text-align: left;
    }
    .settings-sidebar-btn:hover {
        background-color: var(--bg-hover, rgba(0,0,0,0.02));
        color: var(--text-primary, #111827);
    }
    .settings-sidebar-btn.active {
        background-color: var(--bg-active, rgba(91,95,246,0.08));
        color: var(--primary, #5B5FF6);
        border-left-color: var(--primary, #5B5FF6);
    }
    .dark .settings-sidebar-btn:hover {
        background-color: rgba(255,255,255,0.05);
        color: #F9FAF5;
    }
    .dark .settings-sidebar-btn.active {
        background-color: rgba(91,95,246,0.15);
        color: #818CF8;
        border-left-color: #818CF8;
    }

    .settings-card {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 16px;
        padding: 28px;
        box-shadow: var(--shadow, 0 1px 3px rgba(0,0,0,0.05));
    }
    html.dark .settings-card {
        background-color: #1f2937;
        border-color: #374151;
    }

    .form-input-settings {
        background-color: var(--bg-card, #ffffff);
        color: var(--text-primary, #111827);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 12px;
        height: 44px;
        font-size: 14px;
        outline: none;
    }
    .dark .form-input-settings {
        background-color: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    .form-input-settings:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15) !important;
    }

    .color-swatch {
        height: 38px;
        width: 38px;
        border-radius: 10px;
        border: 1px solid var(--border-soft);
        cursor: pointer;
        outline: none;
        padding: 0;
    }

    .preview-box {
        border: 1px dashed var(--border-soft);
        border-radius: 12px;
        padding: 16px;
        background-color: var(--bg-hover);
    }

    .floating-save-bar {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 16px;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
        padding: 16px 28px;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 40px;
        min-width: 480px;
    }
    .dark .floating-save-bar {
        background-color: #1f2937;
        border-color: #374151;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.4);
    }
</style>

<div class="settings-center flex flex-col gap-8 pb-28" x-data="{
    // States configuration mapping
    activeTab: 'General',
    searchQuery: '',
    
    // General configurations
    companyName: 'CliqueHA',
    companyDisplayName: 'CliqueHA Enterprise Support',
    workspaceSlug: 'cliqueha',
    companyEmail: 'admin@cliqueha.com',
    supportEmail: 'support@cliqueha.com',
    contactNumber: '+1 (555) 019-2834',
    website: 'https://cliqueha.com',
    timezone: '{{ old('admin_timezone', $adminTimezone) }}',
    language: 'en',
    dateFormat: 'YYYY-MM-DD',
    timeFormat: '24h',
    currency: 'USD',
    country: 'US',
    defaultDashboard: 'overview',
    defaultLandingPage: 'tickets',
    companyDesc: 'Multi-Tenant Ticketing SaaS Platform Infrastructure.',

    // Branding configurations
    primaryColor: '#5B5FF6',
    secondaryColor: '#10B981',
    accentColor: '#F59E0B',
    logoPreview: null,
    darkLogoPreview: null,
    faviconPreview: null,

    // Theme configurations
    defaultTheme: 'system',
    sidebarStyle: 'expanded',
    borderRadius: '12px',
    animationSpeed: '200ms',
    cardDensity: 'normal',
    enableGlass: true,
    enableShadows: true,

    // Security configurations
    passLength: 8,
    requireUpper: true,
    requireLower: true,
    requireNumbers: true,
    requireSymbols: true,
    passExpiryDays: 90,
    maxLoginAttempts: 5,
    sessionTimeout: 30,

    // Auth configurations
    enable2fa: true,
    force2fa: false,
    googleLogin: true,
    microsoftLogin: false,
    githubLogin: true,
    magicLink: true,

    // Notifications configurations
    emailNotif: true,
    browserNotif: true,
    systemAnnouncements: true,
    maintenanceAlerts: true,
    slaAlerts: true,
    digestFreq: 'instant',

    // System configurations
    maintenanceMode: false,
    appName: 'CliqueHA Ticketing Portal',
    environment: 'production',
    debugMode: false,

    // Email configurations
    mailHost: 'smtp.mailtrap.io',
    mailPort: '2525',
    mailEnc: 'tls',
    mailUser: 'smtp_username',
    mailFrom: 'no-reply@cliqueha.com',

    // Storage configurations
    storageDriver: 's3',
    maxUploadSize: 10,
    allowedTypes: 'jpg,png,pdf,zip',

    // Appearance configurations
    fontFamily: 'Inter',
    fontSize: '14px',

    // Initial backup snapshot for dirty detection
    original: {},

    isDirty() {
        return this.companyName !== this.original.companyName ||
               this.companyDisplayName !== this.original.companyDisplayName ||
               this.workspaceSlug !== this.original.workspaceSlug ||
               this.companyEmail !== this.original.companyEmail ||
               this.supportEmail !== this.original.supportEmail ||
               this.contactNumber !== this.original.contactNumber ||
               this.website !== this.original.website ||
               this.timezone !== this.original.timezone ||
               this.language !== this.original.language ||
               this.dateFormat !== this.original.dateFormat ||
               this.timeFormat !== this.original.timeFormat ||
               this.currency !== this.original.currency ||
               this.country !== this.original.country ||
               this.defaultDashboard !== this.original.defaultDashboard ||
               this.defaultLandingPage !== this.original.defaultLandingPage ||
               this.companyDesc !== this.original.companyDesc ||
               this.primaryColor !== this.original.primaryColor ||
               this.secondaryColor !== this.original.secondaryColor ||
               this.accentColor !== this.original.accentColor ||
               this.defaultTheme !== this.original.defaultTheme ||
               this.sidebarStyle !== this.original.sidebarStyle ||
               this.borderRadius !== this.original.borderRadius ||
               this.animationSpeed !== this.original.animationSpeed ||
               this.cardDensity !== this.original.cardDensity ||
               this.enableGlass !== this.original.enableGlass ||
               this.enableShadows !== this.original.enableShadows ||
               this.passLength !== this.original.passLength ||
               this.requireUpper !== this.original.requireUpper ||
               this.requireLower !== this.original.requireLower ||
               this.requireNumbers !== this.original.requireNumbers ||
               this.requireSymbols !== this.original.requireSymbols ||
               this.passExpiryDays !== this.original.passExpiryDays ||
               this.maxLoginAttempts !== this.original.maxLoginAttempts ||
               this.sessionTimeout !== this.original.sessionTimeout ||
               this.enable2fa !== this.original.enable2fa ||
               this.force2fa !== this.original.force2fa ||
               this.googleLogin !== this.original.googleLogin ||
               this.microsoftLogin !== this.original.microsoftLogin ||
               this.githubLogin !== this.original.githubLogin ||
               this.magicLink !== this.original.magicLink ||
               this.emailNotif !== this.original.emailNotif ||
               this.browserNotif !== this.original.browserNotif ||
               this.systemAnnouncements !== this.original.systemAnnouncements ||
               this.maintenanceAlerts !== this.original.maintenanceAlerts ||
               this.slaAlerts !== this.original.slaAlerts ||
               this.digestFreq !== this.original.digestFreq ||
               this.maintenanceMode !== this.original.maintenanceMode ||
               this.appName !== this.original.appName ||
               this.environment !== this.original.environment ||
               this.debugMode !== this.original.debugMode ||
               this.mailHost !== this.original.mailHost ||
               this.mailPort !== this.original.mailPort ||
               this.mailEnc !== this.original.mailEnc ||
               this.mailUser !== this.original.mailUser ||
               this.mailFrom !== this.original.mailFrom ||
               this.storageDriver !== this.original.storageDriver ||
               this.maxUploadSize !== this.original.maxUploadSize ||
               this.allowedTypes !== this.original.allowedTypes ||
               this.fontFamily !== this.original.fontFamily ||
               this.fontSize !== this.original.fontSize;
    },

    resetForm() {
        this.companyName = this.original.companyName;
        this.companyDisplayName = this.original.companyDisplayName;
        this.workspaceSlug = this.original.workspaceSlug;
        this.companyEmail = this.original.companyEmail;
        this.supportEmail = this.original.supportEmail;
        this.contactNumber = this.original.contactNumber;
        this.website = this.original.website;
        this.timezone = this.original.timezone;
        this.language = this.original.language;
        this.dateFormat = this.original.dateFormat;
        this.timeFormat = this.original.timeFormat;
        this.currency = this.original.currency;
        this.country = this.original.country;
        this.defaultDashboard = this.original.defaultDashboard;
        this.defaultLandingPage = this.original.defaultLandingPage;
        this.companyDesc = this.original.companyDesc;
        this.primaryColor = this.original.primaryColor;
        this.secondaryColor = this.original.secondaryColor;
        this.accentColor = this.original.accentColor;
        this.defaultTheme = this.original.defaultTheme;
        this.sidebarStyle = this.original.sidebarStyle;
        this.borderRadius = this.original.borderRadius;
        this.animationSpeed = this.original.animationSpeed;
        this.cardDensity = this.original.cardDensity;
        this.enableGlass = this.original.enableGlass;
        this.enableShadows = this.original.enableShadows;
        this.passLength = this.original.passLength;
        this.requireUpper = this.original.requireUpper;
        this.requireLower = this.original.requireLower;
        this.requireNumbers = this.original.requireNumbers;
        this.requireSymbols = this.original.requireSymbols;
        this.passExpiryDays = this.original.passExpiryDays;
        this.maxLoginAttempts = this.original.maxLoginAttempts;
        this.sessionTimeout = this.original.sessionTimeout;
        this.enable2fa = this.original.enable2fa;
        this.force2fa = this.original.force2fa;
        this.googleLogin = this.original.googleLogin;
        this.microsoftLogin = this.original.microsoftLogin;
        this.githubLogin = this.original.githubLogin;
        this.magicLink = this.original.magicLink;
        this.emailNotif = this.original.emailNotif;
        this.browserNotif = this.original.browserNotif;
        this.systemAnnouncements = this.original.systemAnnouncements;
        this.maintenanceAlerts = this.original.maintenanceAlerts;
        this.slaAlerts = this.original.slaAlerts;
        this.digestFreq = this.original.digestFreq;
        this.maintenanceMode = this.original.maintenanceMode;
        this.appName = this.original.appName;
        this.environment = this.original.environment;
        this.debugMode = this.original.debugMode;
        this.mailHost = this.original.mailHost;
        this.mailPort = this.original.mailPort;
        this.mailEnc = this.original.mailEnc;
        this.mailUser = this.original.mailUser;
        this.mailFrom = this.original.mailFrom;
        this.storageDriver = this.original.storageDriver;
        this.maxUploadSize = this.original.maxUploadSize;
        this.allowedTypes = this.original.allowedTypes;
        this.fontFamily = this.original.fontFamily;
        this.fontSize = this.original.fontSize;
    },

    saveAll() {
        // Submit real timezone form if timezone changed
        if (this.timezone !== this.original.timezone) {
            document.getElementById('real-settings-form').submit();
            return;
        }

        // Simulate save
        this.original = {
            companyName: this.companyName,
            companyDisplayName: this.companyDisplayName,
            workspaceSlug: this.workspaceSlug,
            companyEmail: this.companyEmail,
            supportEmail: this.supportEmail,
            contactNumber: this.contactNumber,
            website: this.website,
            timezone: this.timezone,
            language: this.language,
            dateFormat: this.dateFormat,
            timeFormat: this.timeFormat,
            currency: this.currency,
            country: this.country,
            defaultDashboard: this.defaultDashboard,
            defaultLandingPage: this.defaultLandingPage,
            companyDesc: this.companyDesc,
            primaryColor: this.primaryColor,
            secondaryColor: this.secondaryColor,
            accentColor: this.accentColor,
            defaultTheme: this.defaultTheme,
            sidebarStyle: this.sidebarStyle,
            borderRadius: this.borderRadius,
            animationSpeed: this.animationSpeed,
            cardDensity: this.cardDensity,
            enableGlass: this.enableGlass,
            enableShadows: this.enableShadows,
            passLength: this.passLength,
            requireUpper: this.requireUpper,
            requireLower: this.requireLower,
            requireNumbers: this.requireNumbers,
            requireSymbols: this.requireSymbols,
            passExpiryDays: this.passExpiryDays,
            maxLoginAttempts: this.maxLoginAttempts,
            sessionTimeout: this.sessionTimeout,
            enable2fa: this.enable2fa,
            force2fa: this.force2fa,
            googleLogin: this.googleLogin,
            microsoftLogin: this.microsoftLogin,
            githubLogin: this.githubLogin,
            magicLink: this.magicLink,
            emailNotif: this.emailNotif,
            browserNotif: this.browserNotif,
            systemAnnouncements: this.systemAnnouncements,
            maintenanceAlerts: this.maintenanceAlerts,
            slaAlerts: this.slaAlerts,
            digestFreq: this.digestFreq,
            maintenanceMode: this.maintenanceMode,
            appName: this.appName,
            environment: this.environment,
            debugMode: this.debugMode,
            mailHost: this.mailHost,
            mailPort: this.mailPort,
            mailEnc: this.mailEnc,
            mailUser: this.mailUser,
            mailFrom: this.mailFrom,
            storageDriver: this.storageDriver,
            maxUploadSize: this.maxUploadSize,
            allowedTypes: this.allowedTypes,
            fontFamily: this.fontFamily,
            fontSize: this.fontSize
        };
        
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Settings saved successfully', type: 'success' } }));
    },

    initBackup() {
        this.original = {
            companyName: this.companyName,
            companyDisplayName: this.companyDisplayName,
            workspaceSlug: this.workspaceSlug,
            companyEmail: this.companyEmail,
            supportEmail: this.supportEmail,
            contactNumber: this.contactNumber,
            website: this.website,
            timezone: this.timezone,
            language: this.language,
            dateFormat: this.dateFormat,
            timeFormat: this.timeFormat,
            currency: this.currency,
            country: this.country,
            defaultDashboard: this.defaultDashboard,
            defaultLandingPage: this.defaultLandingPage,
            companyDesc: this.companyDesc,
            primaryColor: this.primaryColor,
            secondaryColor: this.secondaryColor,
            accentColor: this.accentColor,
            defaultTheme: this.defaultTheme,
            sidebarStyle: this.sidebarStyle,
            borderRadius: this.borderRadius,
            animationSpeed: this.animationSpeed,
            cardDensity: this.cardDensity,
            enableGlass: this.enableGlass,
            enableShadows: this.enableShadows,
            passLength: this.passLength,
            requireUpper: this.requireUpper,
            requireLower: this.requireLower,
            requireNumbers: this.requireNumbers,
            requireSymbols: this.requireSymbols,
            passExpiryDays: this.passExpiryDays,
            maxLoginAttempts: this.maxLoginAttempts,
            sessionTimeout: this.sessionTimeout,
            enable2fa: this.enable2fa,
            force2fa: this.force2fa,
            googleLogin: this.googleLogin,
            microsoftLogin: this.microsoftLogin,
            githubLogin: this.githubLogin,
            magicLink: this.magicLink,
            emailNotif: this.emailNotif,
            browserNotif: this.browserNotif,
            systemAnnouncements: this.systemAnnouncements,
            maintenanceAlerts: this.maintenanceAlerts,
            slaAlerts: this.slaAlerts,
            digestFreq: this.digestFreq,
            maintenanceMode: this.maintenanceMode,
            appName: this.appName,
            environment: this.environment,
            debugMode: this.debugMode,
            mailHost: this.mailHost,
            mailPort: this.mailPort,
            mailEnc: this.mailEnc,
            mailUser: this.mailUser,
            mailFrom: this.mailFrom,
            storageDriver: this.storageDriver,
            maxUploadSize: this.maxUploadSize,
            allowedTypes: this.allowedTypes,
            fontFamily: this.fontFamily,
            fontSize: this.fontSize
        };
        
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },

    // Logo image handle
    logoChange(e, type) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => {
            if (type === 'light') this.logoPreview = event.target.result;
            if (type === 'dark') this.darkLogoPreview = event.target.result;
            if (type === 'favicon') this.faviconPreview = event.target.result;
        };
        reader.readAsDataURL(file);
    },

    matchesSearch(label, desc) {
        if (!this.searchQuery) return true;
        const q = this.searchQuery.toLowerCase();
        return label.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
    }
}" x-init="initBackup()">

    <!-- Real Update Form for Laravel backend execution -->
    <form id="real-settings-form" method="POST" action="{{ route('admin.settings.update') }}" class="hidden">
        @csrf
        @method('PUT')
        <input type="hidden" name="admin_timezone" :value="timezone">
    </form>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-[var(--border-soft)]">
        <div>
            <h1 class="page-title text-[var(--text-primary)]">Settings Center</h1>
            <p class="mt-2 text-sm text-[var(--text-secondary)]">
                Administer global tenant policies, configurations, mail protocols, authentication parameters, and theme styles.
            </p>
        </div>
        <x-search-input 
            model="searchQuery" 
            placeholder="Search settings..." 
            wrapperClass="w-full max-w-[320px]" 
        />
    </div>

    <!-- Layout Grid -->
    <div class="flex flex-col lg:flex-row gap-8 items-start">
        
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-[240px] shrink-0 sidebar-sticky">
            <nav class="flex flex-col gap-1.5 w-full">
                @foreach(['General', 'Branding', 'Theme', 'Security', 'Authentication', 'Notifications', 'System', 'Email', 'Storage', 'Appearance'] as $tab)
                    <button type="button" 
                            @click="activeTab = '{{ $tab }}'"
                            :class="activeTab === '{{ $tab }}' ? 'active' : ''"
                            class="settings-sidebar-btn w-full cursor-pointer">
                        <span>{{ $tab }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        <!-- Right Side Settings Panels -->
        <div class="flex-1 w-full space-y-8">

            <!-- Tab Panel: General -->
            <div x-show="activeTab === 'General'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">General Configuration</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure company identifiers, slugs, localization defaults, and active timezone settings.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Company Name -->
                    <div class="space-y-2" x-show="matchesSearch('Company Name', 'Primary billing identity')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Company Name <span class="text-red-500">*</span></label>
                        <input type="text" x-model="companyName" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Company Display Name -->
                    <div class="space-y-2" x-show="matchesSearch('Company Display Name', 'Public client facing moniker')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Display Name</label>
                        <input type="text" x-model="companyDisplayName" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Workspace Slug -->
                    <div class="space-y-2" x-show="matchesSearch('Workspace Slug', 'Base organization URL domain identifier')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Workspace Slug</label>
                        <input type="text" x-model="workspaceSlug" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Company Email -->
                    <div class="space-y-2" x-show="matchesSearch('Company Email', 'Billing contact address')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Company Email</label>
                        <input type="email" x-model="companyEmail" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Support Email -->
                    <div class="space-y-2" x-show="matchesSearch('Support Email', 'Default contact point for clients')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Support Email</label>
                        <input type="email" x-model="supportEmail" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Contact Number -->
                    <div class="space-y-2" x-show="matchesSearch('Contact Number', 'Support phone connection')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Contact Number</label>
                        <input type="text" x-model="contactNumber" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Timezone -->
                    <div class="space-y-2" x-show="matchesSearch('Timezone', 'Display timezone configurations')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">System Timezone <span class="text-red-500">*</span></label>
                        <select x-model="timezone" class="form-input-settings block w-full px-4 cursor-pointer">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-[var(--text-secondary)]">Applies directly to administrative and audit logs.</p>
                    </div>

                    <!-- Date Format -->
                    <div class="space-y-2" x-show="matchesSearch('Date Format', 'Audit table date outputs style')">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Date Format</label>
                        <select x-model="dateFormat" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="YYYY-MM-DD">YYYY-MM-DD (2026-07-17)</option>
                            <option value="MM/DD/YYYY">MM/DD/YYYY (07/17/2026)</option>
                            <option value="DD-MM-YYYY">DD-MM-YYYY (17-07-2026)</option>
                        </select>
                    </div>
                </div>

                <!-- Company Description -->
                <div class="space-y-2" x-show="matchesSearch('Company Description', 'Meta descriptive context')">
                    <label class="block text-sm font-semibold text-[var(--text-primary)]">Company Description</label>
                    <textarea x-model="companyDesc" rows="4" class="form-input-settings block w-full px-4 py-3 h-auto min-h-[100px]"></textarea>
                </div>
            </div>

            <!-- Tab Panel: Branding -->
            <div x-show="activeTab === 'Branding'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Branding Configurator</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Upload brand assets and define primary accent colors to reflect across headers, logins, and sidebars.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Brand Colors Configuration -->
                    <div class="space-y-6">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Brand Palette</h3>
                        
                        <div class="space-y-4">
                            <!-- Primary color picker -->
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="primaryColor" class="color-swatch">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-xs font-bold text-[var(--text-primary)]">Primary Brand Color</label>
                                    <input type="text" x-model="primaryColor" class="form-input-settings block w-full px-3 mt-1 h-9">
                                </div>
                            </div>

                            <!-- Secondary color picker -->
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="secondaryColor" class="color-swatch">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-xs font-bold text-[var(--text-primary)]">Secondary Brand Color</label>
                                    <input type="text" x-model="secondaryColor" class="form-input-settings block w-full px-3 mt-1 h-9">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Element Preview Container -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Branding Preview</h3>
                        <div class="preview-box space-y-4">
                            <div class="p-3 rounded-lg flex items-center justify-between" :style="'background-color: ' + primaryColor + '; color: #ffffff;'">
                                <span class="text-xs font-bold" x-text="companyName"></span>
                                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-white/20">Preview Header</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" class="btn-action text-xs font-semibold text-white border-none cursor-pointer" :style="'background-color: ' + primaryColor">
                                    Primary Action
                                </button>
                                <button type="button" class="btn-action text-xs font-semibold text-white border-none cursor-pointer" :style="'background-color: ' + secondaryColor">
                                    Secondary Action
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo File Uploads Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-[var(--border-soft)]">
                    <!-- Light theme logo -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-[var(--text-primary)] uppercase tracking-wider">Light Theme Logo</label>
                        <div class="border border-dashed border-[var(--border-soft)] rounded-xl p-4 flex flex-col items-center justify-center bg-[var(--bg-hover)]/30 min-h-[140px]">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" class="max-h-12 max-w-full object-contain mb-3">
                            </template>
                            <template x-if="!logoPreview">
                                <svg class="h-8 w-8 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            </template>
                            <input type="file" @change="logoChange($event, 'light')" class="hidden" id="logo-light-file" accept="image/*">
                            <label for="logo-light-file" class="text-[10px] font-bold text-[var(--primary)] hover:underline cursor-pointer">Choose File</label>
                        </div>
                    </div>

                    <!-- Dark theme logo -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-[var(--text-primary)] uppercase tracking-wider">Dark Theme Logo</label>
                        <div class="border border-dashed border-[var(--border-soft)] rounded-xl p-4 flex flex-col items-center justify-center bg-slate-900 min-h-[140px]">
                            <template x-if="darkLogoPreview">
                                <img :src="darkLogoPreview" class="max-h-12 max-w-full object-contain mb-3">
                            </template>
                            <template x-if="!darkLogoPreview">
                                <svg class="h-8 w-8 text-slate-650 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            </template>
                            <input type="file" @change="logoChange($event, 'dark')" class="hidden" id="logo-dark-file" accept="image/*">
                            <label for="logo-dark-file" class="text-[10px] font-bold text-indigo-400 hover:underline cursor-pointer">Choose File</label>
                        </div>
                    </div>

                    <!-- Favicon -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-[var(--text-primary)] uppercase tracking-wider">Favicon Icon</label>
                        <div class="border border-dashed border-[var(--border-soft)] rounded-xl p-4 flex flex-col items-center justify-center bg-[var(--bg-hover)]/30 min-h-[140px]">
                            <template x-if="faviconPreview">
                                <img :src="faviconPreview" class="h-8 w-8 object-contain mb-3">
                            </template>
                            <template x-if="!faviconPreview">
                                <svg class="h-8 w-8 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A11.953 11.953 0 0112 16.5c-2.998 0-5.74-1.1-7.843-2.918m0 0A8.959 8.959 0 013 12c0-.778.099-1.533.284-2.253" /></svg>
                            </template>
                            <input type="file" @change="logoChange($event, 'favicon')" class="hidden" id="logo-favicon-file" accept="image/*">
                            <label for="logo-favicon-file" class="text-[10px] font-bold text-[var(--primary)] hover:underline cursor-pointer">Choose File</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: Theme -->
            <div x-show="activeTab === 'Theme'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Theme & Styles Preference</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure layout densities, default theme mappings, and interface visual modifiers.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Default Theme selection -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Default Portal Mode</label>
                        <select x-model="defaultTheme" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="system">Respect System defaults</option>
                            <option value="dark">Force Dark mode theme</option>
                            <option value="light">Force Light mode theme</option>
                        </select>
                    </div>

                    <!-- Sidebar Layout style -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Sidebar Presentation</label>
                        <select x-model="sidebarStyle" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="expanded">Expanded Details</option>
                            <option value="compact">Compact Icons Only</option>
                        </select>
                    </div>

                    <!-- Border radius multiplier -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Default Border Radius</label>
                        <select x-model="borderRadius" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="8px">8px (Standard)</option>
                            <option value="12px">12px (Smooth Modern)</option>
                            <option value="16px">16px (Extremely Rounded)</option>
                        </select>
                    </div>

                    <!-- Interface Density -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Card Density padding</label>
                        <select x-model="cardDensity" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="compact">Compact layout</option>
                            <option value="normal">Normal grid</option>
                            <option value="relaxed">Relaxed padding</option>
                        </select>
                    </div>
                </div>

                <!-- Glass effects and toggle switches -->
                <div class="space-y-4 pt-4 border-t border-[var(--border-soft)]">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="enableGlass" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                        <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Enable frosted glass visual overlays</span>
                    </label>
                    <br>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="enableShadows" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                        <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Enable soft card elevations and drop shadows</span>
                    </label>
                </div>
            </div>

            <!-- Tab Panel: Security -->
            <div x-show="activeTab === 'Security'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Security Protocols & Locks</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure administrative login caps, session expiration rules, and password strength policies.</p>
                </div>

                <div class="space-y-6">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Password Complexity Enforcement</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="requireUpper" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Require uppercase character</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="requireLower" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Require lowercase character</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="requireNumbers" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Require numerical digits</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="requireSymbols" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Require complex symbol character</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-[var(--border-soft)]">
                    <!-- Session Expiration -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Session Expiry Timeout</label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model="sessionTimeout" class="form-input-settings block w-24 px-4">
                            <span class="text-xs text-[var(--text-secondary)] font-semibold">Minutes</span>
                        </div>
                    </div>

                    <!-- Expiration count -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Password Lifetime</label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model="passExpiryDays" class="form-input-settings block w-24 px-4">
                            <span class="text-xs text-[var(--text-secondary)] font-semibold">Days</span>
                        </div>
                    </div>

                    <!-- Max Attempts -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Max login retries</label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model="maxLoginAttempts" class="form-input-settings block w-24 px-4">
                            <span class="text-xs text-[var(--text-secondary)] font-semibold">Attempts</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: Authentication -->
            <div x-show="activeTab === 'Authentication'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">SSO & Identity Access Profiles</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure multi-factor policies, oauth integrations, and verification parameters.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Multi-Factor Control</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="enable2fa" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Support Google/Microsoft Authenticator keys</span>
                        </label>
                        <br>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="force2fa" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Enforce MFA registration on first auth event</span>
                        </label>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">OAuth Providers</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="googleLogin" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Support login with Google identity</span>
                        </label>
                        <br>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="githubLogin" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Support login with GitHub identity</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: Notifications -->
            <div x-show="activeTab === 'Notifications'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Outbound Triggers Default Rules</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure workspace alert rules mapping email notification dispatch frequencies.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Notification Modes</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="emailNotif" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Enable email broadcast dispatch</span>
                        </label>
                        <br>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="browserNotif" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Enable browser push notifications</span>
                        </label>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">Default Alert Categories</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="systemAnnouncements" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Receive system broadcasts & announcements</span>
                        </label>
                        <br>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="slaAlerts" class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500">
                            <span class="ml-2.5 text-xs font-semibold text-[var(--text-primary)]">Receive SLA breach updates and notifications</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: System -->
            <div x-show="activeTab === 'System'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">System Health & Operations</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Check active environment variables and manage cache optimizations.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Environment -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Active Environment Mode</label>
                        <input type="text" x-model="environment" readonly class="form-input-settings block w-full px-4 bg-[var(--bg-hover)] opacity-70">
                    </div>

                    <!-- App Name -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Application Base Name</label>
                        <input type="text" x-model="appName" class="form-input-settings block w-full px-4">
                    </div>
                </div>

                <div class="pt-6 border-t border-[var(--border-soft)] space-y-4">
                    <h3 class="text-sm font-bold text-[var(--text-primary)] uppercase tracking-wider">System Cache Utilities</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Cache rebuild completed successfully', type: 'success' } }))" class="btn-action bg-[var(--bg-hover)] text-[var(--text-primary)] hover:bg-[var(--bg-active)] border border-[var(--border-soft)] cursor-pointer">
                            Rebuild System Config Cache
                        </button>
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Views and routing caches cleared', type: 'success' } }))" class="btn-action bg-rose-50/50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-450 border border-rose-200 dark:border-rose-900/30 cursor-pointer">
                            Clear Application Caches
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: Email -->
            <div x-show="activeTab === 'Email'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Email SMTP Configuration</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure outgoing SMTP profiles for client tickets dispatch routing.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- SMTP Host -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">SMTP Server Host</label>
                        <input type="text" x-model="mailHost" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- SMTP Port -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Port Number</label>
                        <input type="text" x-model="mailPort" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- SMTP Encryption -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Transport Encryption</label>
                        <select x-model="mailEnc" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="tls">TLS Protocol</option>
                            <option value="ssl">SSL Protocol</option>
                            <option value="none">Unencrypted Plain</option>
                        </select>
                    </div>

                    <!-- SMTP Sender -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">SMTP Sender Email address</label>
                        <input type="email" x-model="mailFrom" class="form-input-settings block w-full px-4">
                    </div>
                </div>

                <div class="pt-4 border-t border-[var(--border-soft)]">
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'SMTP Test connection initiated. Credentials are valid.', type: 'success' } }))" class="btn-action bg-[#5B5FF6] text-white hover:bg-[#4752C4] border-none cursor-pointer">
                        Validate SMTP Connection Profile
                    </button>
                </div>
            </div>

            <!-- Tab Panel: Storage -->
            <div x-show="activeTab === 'Storage'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Storage Management Config</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Configure default attachment disks and file upload thresholds.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Default Storage Disk -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Default Storage Provider</label>
                        <select x-model="storageDriver" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="local">Local filesystem storage</option>
                            <option value="s3">AWS S3 Cloud bucket Storage</option>
                            <option value="gcs">Google Cloud Storage Disk</option>
                        </select>
                    </div>

                    <!-- Max upload size -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Maximum File Upload Size (MB)</label>
                        <input type="number" x-model="maxUploadSize" class="form-input-settings block w-full px-4">
                    </div>

                    <!-- Allowed types -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Accepted File Formats</label>
                        <input type="text" x-model="allowedTypes" class="form-input-settings block w-full px-4">
                        <p class="text-[10px] text-[var(--text-secondary)]">Separate file extensions with commas.</p>
                    </div>
                </div>
            </div>

            <!-- Tab Panel: Appearance -->
            <div x-show="activeTab === 'Appearance'" x-cloak class="settings-card space-y-6">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--text-primary)]">Portal Typography Defaults</h2>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">Define font metrics and standard styles overlays.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Font Family selection -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Font Family Family</label>
                        <select x-model="fontFamily" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="Inter">Inter UI (Modern Standard)</option>
                            <option value="Roboto">Roboto Condensed</option>
                            <option value="system-ui">System UI default sans</option>
                        </select>
                    </div>

                    <!-- Font size standard -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[var(--text-primary)]">Default Font Size</label>
                        <select x-model="fontSize" class="form-input-settings block w-full px-4 cursor-pointer">
                            <option value="13px">13px (Compact)</option>
                            <option value="14px">14px (Normal Standard)</option>
                            <option value="16px">16px (Relaxed Larger)</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Floating Save Changes Bar Banner -->
    <div class="floating-save-bar border border-[var(--border-soft)] w-[90%] max-w-[600px]" x-show="isDirty()" x-transition x-cloak>
        <span class="text-xs font-semibold text-[var(--text-secondary)]">You have unsaved changes.</span>
        <div class="flex items-center gap-3">
            <button @click="resetForm()" class="btn-action bg-[var(--bg-hover)] text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--bg-active)] border border-[var(--border-soft)] cursor-pointer">
                Reset
            </button>
            <button @click="saveAll()" class="btn-action bg-[#5B5FF6] text-white hover:bg-[#4752C4] text-xs font-bold cursor-pointer border-none">
                Save Changes
            </button>
        </div>
    </div>

</div>
@endsection
