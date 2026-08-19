<?php

use App\Http\Controllers\Admin\ActiveHistory\AdminActiveHistoryController;
use App\Http\Controllers\Admin\Company\Create\AdminCompanyCreateController;
use App\Http\Controllers\Admin\Company\Delete\AdminCompanyDeleteController;
use App\Http\Controllers\Admin\Company\Edit\AdminCompanyEditController;
use App\Http\Controllers\Admin\Company\Export\AdminCompanyExportController;
use App\Http\Controllers\Admin\Company\Import\AdminCompanyImportCommitController;
use App\Http\Controllers\Admin\Company\Import\AdminCompanyImportPageController;
use App\Http\Controllers\Admin\Company\Import\AdminCompanyImportPreviewController;
use App\Http\Controllers\Admin\Company\Import\AdminCompanyImportTemplateController;
use App\Http\Controllers\Admin\Company\List\AdminCompanyListController;
use App\Http\Controllers\Admin\Company\List\AdminCompanyMembersController;
use App\Http\Controllers\Admin\Company\List\AdminCompanyMembersExportController;
use App\Http\Controllers\Admin\Company\Store\AdminCompanyStoreController;
use App\Http\Controllers\Admin\Company\Update\AdminCompanyUpdateController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\Contract\Create\AdminContractCreateController;
use App\Http\Controllers\Admin\Contract\Delete\AdminContractDeleteController;
use App\Http\Controllers\Admin\Contract\Edit\AdminContractEditController;
use App\Http\Controllers\Admin\Contract\List\AdminContractListController;
use App\Http\Controllers\Admin\Contract\Show\AdminContractShowController;
use App\Http\Controllers\Admin\Contract\Store\AdminContractStoreController;
use App\Http\Controllers\Admin\Contract\Update\AdminContractUpdateController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\Facility\Create\AdminFacilityCreateController;
use App\Http\Controllers\Admin\Facility\Delete\AdminFacilityDeleteController;
use App\Http\Controllers\Admin\Facility\Edit\AdminFacilityEditController;
use App\Http\Controllers\Admin\Facility\Export\AdminFacilityExportController;
use App\Http\Controllers\Admin\Facility\Import\AdminFacilityImportCommitController;
use App\Http\Controllers\Admin\Facility\Import\AdminFacilityImportPageController;
use App\Http\Controllers\Admin\Facility\Import\AdminFacilityImportPreviewController;
use App\Http\Controllers\Admin\Facility\List\AdminFacilityListController;
use App\Http\Controllers\Admin\Facility\Logs\AdminFacilityLogsController;
use App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationExportController;
use App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationImportController;
use App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationPageController;
use App\Http\Controllers\Admin\Facility\Seo\AdminFacilitySeoGenerateController;
use App\Http\Controllers\Admin\Facility\Show\AdminFacilityShowController;
use App\Http\Controllers\Admin\Facility\Store\AdminFacilityStoreController;
use App\Http\Controllers\Admin\Facility\Update\AdminFacilityUpdateController;
use App\Http\Controllers\Admin\FacilityBranch\Create\AdminFacilityBranchCreateController;
use App\Http\Controllers\Admin\FacilityBranch\Delete\AdminFacilityBranchDeleteController;
use App\Http\Controllers\Admin\FacilityBranch\Edit\AdminFacilityBranchEditController;
use App\Http\Controllers\Admin\FacilityBranch\Export\AdminFacilityBranchExportController;
// Governorate Routes
use App\Http\Controllers\Admin\FacilityBranch\Import\AdminFacilityBranchImportCommitController;
use App\Http\Controllers\Admin\FacilityBranch\Import\AdminFacilityBranchImportPageController;
use App\Http\Controllers\Admin\FacilityBranch\Import\AdminFacilityBranchImportPreviewController;
use App\Http\Controllers\Admin\FacilityBranch\List\AdminFacilityBranchListController;
use App\Http\Controllers\Admin\FacilityBranch\Logs\AdminFacilityBranchLogsController;
use App\Http\Controllers\Admin\FacilityBranch\Show\AdminFacilityBranchShowController;
use App\Http\Controllers\Admin\FacilityBranch\Store\AdminFacilityBranchStoreController;
// FacilityType Routes
use App\Http\Controllers\Admin\FacilityBranch\Update\AdminFacilityBranchUpdateController;
use App\Http\Controllers\Admin\FacilityType\Create\AdminFacilityTypeCreateController;
use App\Http\Controllers\Admin\FacilityType\Delete\AdminFacilityTypeDeleteController;
use App\Http\Controllers\Admin\FacilityType\Edit\AdminFacilityTypeEditController;
use App\Http\Controllers\Admin\FacilityType\List\AdminFacilityTypeListController;
use App\Http\Controllers\Admin\FacilityType\Show\AdminFacilityTypeShowController;
use App\Http\Controllers\Admin\FacilityType\Store\AdminFacilityTypeStoreController;
// Facility Routes
use App\Http\Controllers\Admin\FacilityType\Update\AdminFacilityTypeUpdateController;
use App\Http\Controllers\Admin\Faq\Create\AdminFaqCreateController;
use App\Http\Controllers\Admin\Faq\Delete\AdminFaqDeleteController;
use App\Http\Controllers\Admin\Faq\Edit\AdminFaqEditController;
use App\Http\Controllers\Admin\Faq\List\AdminFaqListController;
use App\Http\Controllers\Admin\Faq\Store\AdminFaqStoreController;
use App\Http\Controllers\Admin\Faq\Update\AdminFaqUpdateController;
use App\Http\Controllers\Admin\Governorate\Create\AdminGovernorateCreateController;
use App\Http\Controllers\Admin\Governorate\Delete\AdminGovernorateDeleteController;
use App\Http\Controllers\Admin\Governorate\Edit\AdminGovernorateEditController;
use App\Http\Controllers\Admin\Governorate\List\AdminGovernorateListController;
use App\Http\Controllers\Admin\Governorate\Show\AdminGovernorateShowController;
// FacilityBranch Routes
use App\Http\Controllers\Admin\Governorate\Store\AdminGovernorateStoreController;
use App\Http\Controllers\Admin\Governorate\Update\AdminGovernorateUpdateController;
use App\Http\Controllers\Admin\MemberPayment\Create\AdminMemberPaymentCreateController;
use App\Http\Controllers\Admin\MemberPayment\Delete\AdminMemberPaymentDeleteController;
use App\Http\Controllers\Admin\MemberPayment\Edit\AdminMemberPaymentEditController;
use App\Http\Controllers\Admin\MemberPayment\Export\AdminMemberPaymentExportToPayController;
use App\Http\Controllers\Admin\MemberPayment\Import\AdminMemberPaymentImportCommitController;
use App\Http\Controllers\Admin\MemberPayment\Import\AdminMemberPaymentImportPageController;
use App\Http\Controllers\Admin\MemberPayment\Import\AdminMemberPaymentImportPreviewController;
use App\Http\Controllers\Admin\MemberPayment\List\AdminMemberPaymentListController;
use App\Http\Controllers\Admin\MemberPayment\Show\AdminMemberPaymentShowController;
use App\Http\Controllers\Admin\MemberPayment\Store\AdminMemberPaymentStoreController;
// Offer Routes
use App\Http\Controllers\Admin\MemberPayment\Update\AdminMemberPaymentUpdateController;
use App\Http\Controllers\Admin\MembershipCard\Create\AdminMembershipCardCreateController;
use App\Http\Controllers\Admin\MembershipCard\Delete\AdminMembershipCardDeleteController;
use App\Http\Controllers\Admin\MembershipCard\List\AdminMembershipCardListController;
use App\Http\Controllers\Admin\MembershipCard\Show\AdminMembershipCardShowController;
use App\Http\Controllers\Admin\MembershipCard\Store\AdminMembershipCardStoreController;
use App\Http\Controllers\Admin\MembershipCard\UploadPdf\AdminMembershipCardUploadPdfController;
// MembershipUsage Routes
use App\Http\Controllers\Admin\MembershipUsage\Create\AdminMembershipUsageCreateController;
use App\Http\Controllers\Admin\MembershipUsage\Delete\AdminMembershipUsageDeleteController;
use App\Http\Controllers\Admin\MembershipUsage\Edit\AdminMembershipUsageEditController;
use App\Http\Controllers\Admin\MembershipUsage\List\AdminMembershipUsageListController;
use App\Http\Controllers\Admin\MembershipUsage\Show\AdminMembershipUsageShowController;
use App\Http\Controllers\Admin\MembershipUsage\Store\AdminMembershipUsageStoreController;
use App\Http\Controllers\Admin\MembershipUsage\Update\AdminMembershipUsageUpdateController;
// MemberPayment Routes
use App\Http\Controllers\Admin\NewsTicker\Create\AdminNewsTickerCreateController;
use App\Http\Controllers\Admin\NewsTicker\Delete\AdminNewsTickerDeleteController;
use App\Http\Controllers\Admin\NewsTicker\Edit\AdminNewsTickerEditController;
use App\Http\Controllers\Admin\NewsTicker\List\AdminNewsTickerListController;
use App\Http\Controllers\Admin\NewsTicker\Store\AdminNewsTickerStoreController;
use App\Http\Controllers\Admin\NewsTicker\Update\AdminNewsTickerUpdateController;
use App\Http\Controllers\Admin\Offer\Create\AdminOfferCreateController;
use App\Http\Controllers\Admin\Offer\Delete\AdminOfferDeleteController;
use App\Http\Controllers\Admin\Offer\Edit\AdminOfferEditController;
use App\Http\Controllers\Admin\Offer\List\AdminOfferListController;
use App\Http\Controllers\Admin\Offer\Show\AdminOfferShowController;
// Company Routes
use App\Http\Controllers\Admin\Offer\Store\AdminOfferStoreController;
use App\Http\Controllers\Admin\Offer\Update\AdminOfferUpdateController;
use App\Http\Controllers\Admin\Partner\Create\AdminPartnerCreateController;
use App\Http\Controllers\Admin\Partner\Delete\AdminPartnerDeleteController;
use App\Http\Controllers\Admin\Partner\Edit\AdminPartnerEditController;
use App\Http\Controllers\Admin\Partner\List\AdminPartnerListController;
use App\Http\Controllers\Admin\Partner\Store\AdminPartnerStoreController;
use App\Http\Controllers\Admin\Partner\Update\AdminPartnerUpdateController;
// Contract Routes
use App\Http\Controllers\Admin\PartnerOffer\Create\AdminPartnerOfferCreateController;
use App\Http\Controllers\Admin\PartnerOffer\Delete\AdminPartnerOfferDeleteController;
use App\Http\Controllers\Admin\PartnerOffer\Edit\AdminPartnerOfferEditController;
use App\Http\Controllers\Admin\PartnerOffer\ForceDelete\AdminPartnerOfferForceDeleteController;
use App\Http\Controllers\Admin\PartnerOffer\List\AdminPartnerOfferListController;
use App\Http\Controllers\Admin\PartnerOffer\Restore\AdminPartnerOfferRestoreController;
use App\Http\Controllers\Admin\PartnerOffer\Store\AdminPartnerOfferStoreController;
// FAQ Routes
use App\Http\Controllers\Admin\PartnerOffer\Trash\AdminPartnerOfferTrashController;
use App\Http\Controllers\Admin\PartnerOffer\Update\AdminPartnerOfferUpdateController;
use App\Http\Controllers\Admin\PartnerOfferRequest\Delete\AdminPartnerOfferRequestDeleteController;
use App\Http\Controllers\Admin\PartnerOfferRequest\List\AdminPartnerOfferRequestListController;
use App\Http\Controllers\Admin\PartnerOfferRequest\Show\AdminPartnerOfferRequestShowController;
use App\Http\Controllers\Admin\Sales\Create\AdminSalesCreateController;
use App\Http\Controllers\Admin\Sales\Delete\AdminSalesDeleteController;
use App\Http\Controllers\Admin\Sales\Edit\AdminSalesEditController;
use App\Http\Controllers\Admin\Sales\Export\AdminSalesExportController;
use App\Http\Controllers\Admin\Sales\Import\AdminSalesImportCommitController;
use App\Http\Controllers\Admin\Sales\Import\AdminSalesImportPageController;
use App\Http\Controllers\Admin\Sales\Import\AdminSalesImportPreviewController;
use App\Http\Controllers\Admin\Sales\Import\AdminSalesImportTemplateController;
use App\Http\Controllers\Admin\Sales\List\AdminSalesListController;
use App\Http\Controllers\Admin\Sales\Store\AdminSalesStoreController;
use App\Http\Controllers\Admin\Sales\Update\AdminSalesUpdateController;
use App\Http\Controllers\Admin\Service\Create\AdminServiceCreateController;
// Partner Routes
use App\Http\Controllers\Admin\Service\Delete\AdminServiceDeleteController;
use App\Http\Controllers\Admin\Service\Edit\AdminServiceEditController;
use App\Http\Controllers\Admin\Service\List\AdminServiceListController;
use App\Http\Controllers\Admin\Service\Show\AdminServiceShowController;
use App\Http\Controllers\Admin\Service\Store\AdminServiceStoreController;
use App\Http\Controllers\Admin\Service\Update\AdminServiceUpdateController;
// PartnerOffer Routes
use App\Http\Controllers\Admin\ServiceType\Create\AdminServiceTypeCreateController;
use App\Http\Controllers\Admin\ServiceType\Delete\AdminServiceTypeDeleteController;
use App\Http\Controllers\Admin\ServiceType\Edit\AdminServiceTypeEditController;
use App\Http\Controllers\Admin\ServiceType\List\AdminServiceTypeListController;
use App\Http\Controllers\Admin\ServiceType\Show\AdminServiceTypeShowController;
use App\Http\Controllers\Admin\ServiceType\Store\AdminServiceTypeStoreController;
use App\Http\Controllers\Admin\ServiceType\Update\AdminServiceTypeUpdateController;
use App\Http\Controllers\Admin\Tag\Create\AdminTagCreateController;
use App\Http\Controllers\Admin\Tag\Delete\AdminTagDeleteController;
// PartnerOfferRequest Routes
use App\Http\Controllers\Admin\Tag\Edit\AdminTagEditController;
use App\Http\Controllers\Admin\Tag\List\AdminTagListController;
use App\Http\Controllers\Admin\Tag\Show\AdminTagShowController;
// Sales Routes
use App\Http\Controllers\Admin\Tag\Store\AdminTagStoreController;
use App\Http\Controllers\Admin\Tag\Update\AdminTagUpdateController;
use App\Http\Controllers\Admin\User\Membership\ActiveHistory\AdminUserMembershipActiveHistoryController;
use App\Http\Controllers\Admin\User\Membership\Create\AdminUserMembershipCreateController;
use App\Http\Controllers\Admin\User\Membership\Delete\AdminUserMembershipDeleteController;
use App\Http\Controllers\Admin\User\Membership\Edit\AdminUserMembershipEditController;
// Service Routes
use App\Http\Controllers\Admin\User\Membership\Export\AdminUserMembershipExportController;
use App\Http\Controllers\Admin\User\Membership\FamilyMember\Delete\AdminFamilyMemberDeleteController;
use App\Http\Controllers\Admin\User\Membership\FamilyMember\Store\AdminFamilyMemberStoreController;
use App\Http\Controllers\Admin\User\Membership\FamilyMember\Update\AdminFamilyMemberUpdateController;
use App\Http\Controllers\Admin\User\Membership\ForceDelete\AdminUserMembershipForceDeleteController;
use App\Http\Controllers\Admin\User\Membership\Import\AdminUserMembershipImportCommitController;
use App\Http\Controllers\Admin\User\Membership\Import\AdminUserMembershipImportExportController;
// ServiceType Routes
use App\Http\Controllers\Admin\User\Membership\Import\AdminUserMembershipImportPageController;
use App\Http\Controllers\Admin\User\Membership\Import\AdminUserMembershipImportPreviewController;
use App\Http\Controllers\Admin\User\Membership\Import\AdminUserMembershipImportTemplateController;
use App\Http\Controllers\Admin\User\Membership\List\AdminUserMembershipListController;
use App\Http\Controllers\Admin\User\Membership\Logs\AdminUserMembershipLogsController;
use App\Http\Controllers\Admin\User\Membership\Restore\AdminUserMembershipRestoreController;
use App\Http\Controllers\Admin\User\Membership\Show\AdminUserMembershipShowController;
use App\Http\Controllers\Admin\User\Membership\Store\AdminUserMembershipStoreController;
use App\Http\Controllers\Admin\User\Membership\Trash\AdminUserMembershipTrashController;
// Tag Routes
use App\Http\Controllers\Admin\User\Membership\Update\AdminUserMembershipUpdateController;
use App\Http\Controllers\Admin\User\Membership\UpdatePassword\AdminUserMembershipUpdatePasswordController;
use App\Http\Controllers\Api\MembershipNumberController;
use App\Http\Controllers\Guest\MembershipController;
use App\Http\Controllers\Guest\MembershipUsage\GuestMembershipUsageCreateController;
use App\Http\Controllers\Guest\MembershipUsage\GuestMembershipUsageStoreController;
use App\Http\Controllers\Guest\PartnerOfferRequestController;
use Illuminate\Support\Facades\Route;

// There are no public landing pages. "/" sends guests to the login screen and
// authenticated users to their own area. See HomeRedirectController for why the
// `home` route name has to stay in place.
Route::get('/', \App\Http\Controllers\HomeRedirectController::class)->name('home');

// Language switching
Route::get('/lang/{locale}', [\App\Http\Controllers\LanguageController::class, 'switch'])->name('lang.switch');

// Former public marketing pages. They now bounce to "/" so that anyone landing
// on an old link (or an old in-app <Link>) ends up on the login screen while
// logged-in users still reach their own area instead of a 404.
Route::redirect('/about', '/')->name('about');
Route::redirect('/contact-us', '/')->name('contact');
Route::redirect('/partners', '/')->name('partners');
Route::redirect('/partners/{facility}', '/')->name('partners.show');

// Partners JSON list (used by /card-generator.html iframe)
Route::get('/api/partners', \App\Http\Controllers\Api\PartnersListController::class)->name('api.partners.list');

// Guest: submit an offer request (phone number + offer)
Route::post('/partner-offer/{partnerOffer}/request', [PartnerOfferRequestController::class, 'store'])->name('guest.partner-offer.request');

// Guest routes (membership)
Route::post('/membership/lookup', [MembershipController::class, 'lookup'])->name('guest.membership.lookup');
Route::get('/membership/{membership}', [MembershipController::class, 'show'])->name('guest.membership.show');
Route::get('/membership/{membership}/usage/create', GuestMembershipUsageCreateController::class)->name('guest.membership-usage.create');
Route::post('/membership/{membership}/usage', GuestMembershipUsageStoreController::class)->name('guest.membership-usage.store');

// Override Jetstream's profile.show route to require the `manage profile`
// permission. Loaded after the Jetstream service provider so this binding wins.
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'permission:manage profile'])
    ->get('/user/profile', [\Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController::class, 'show'])
    ->name('profile.show');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'role:super_admin|admin|editor', \App\Http\Middleware\ShareUserPermissions::class])->group(function () {
    // Dashboard (any admin-area role)
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin');

    // ---- Bulk / cross-cutting membership tools (manage memberships only) ----
    // Trash/restore/force-delete, contact messages, export, and import operate
    // across the whole dataset, so they require the broader permission.
    Route::middleware('permission:manage memberships')->group(function () {
        // Contact Messages Management
        Route::get('/admin/contact-messages', [AdminContactMessageController::class, 'index'])->name('admin.contact-messages.index');
        Route::get('/admin/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('admin.contact-messages.show');
        Route::put('/admin/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'update'])->name('admin.contact-messages.update');
        Route::delete('/admin/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('admin.contact-messages.destroy');
        Route::post('/admin/contact-messages/bulk-update', [AdminContactMessageController::class, 'bulkUpdate'])->name('admin.contact-messages.bulk-update');

        Route::get('/admin/user/membership/import', AdminUserMembershipImportPageController::class)->name('admin.user.membership.import.page');
        Route::post('/admin/user/membership/import/preview', AdminUserMembershipImportPreviewController::class)->name('admin.user.membership.import.preview');
        Route::post('/admin/user/membership/import/commit', AdminUserMembershipImportCommitController::class)->name('admin.user.membership.import.commit');
        Route::get('/admin/user/membership/import/template', AdminUserMembershipImportTemplateController::class)->name('admin.user.membership.import.template');
        Route::get('/admin/user/membership/import/export', AdminUserMembershipImportExportController::class)->name('admin.user.membership.import.export');
    });

    // ---- Card Templates (reusable card designs backing every generated card) ----
    // IMPORTANT: the static /statuses and /layout-defaults routes MUST come
    // before the wildcard /{cardTemplate} route.
    Route::middleware('permission:manage card templates')->group(function () {
        // Dashboard pages. /create MUST come before the /{cardTemplate} wildcard.
        Route::get('/admin/card-templates', \App\Http\Controllers\Admin\CardTemplate\Index\AdminCardTemplateIndexController::class)
            ->name('admin.card-templates.index');
        Route::get('/admin/card-templates/create', \App\Http\Controllers\Admin\CardTemplate\Edit\AdminCardTemplateEditController::class)
            ->name('admin.card-templates.create');
        Route::get('/admin/card-templates/{cardTemplate}/edit', \App\Http\Controllers\Admin\CardTemplate\Edit\AdminCardTemplateEditController::class)
            ->name('admin.card-templates.edit');

        Route::get('/api/card-templates/statuses', \App\Http\Controllers\Admin\CardTemplate\Meta\AdminCardTemplateStatusesController::class)
            ->name('admin.card-templates.statuses');
        Route::get('/api/card-templates/layout-defaults', \App\Http\Controllers\Admin\CardTemplate\Meta\AdminCardTemplateLayoutDefaultsController::class)
            ->name('admin.card-templates.layout-defaults');
        Route::post('/api/card-templates/{cardTemplate}/duplicate', \App\Http\Controllers\Admin\CardTemplate\Duplicate\AdminCardTemplateDuplicateController::class)
            ->name('admin.card-templates.duplicate');

        Route::get('/api/card-templates', \App\Http\Controllers\Admin\CardTemplate\List\AdminCardTemplateListController::class)
            ->name('admin.card-templates.list');
        Route::post('/api/card-templates', \App\Http\Controllers\Admin\CardTemplate\Store\AdminCardTemplateStoreController::class)
            ->name('admin.card-templates.store');
        Route::get('/api/card-templates/{cardTemplate}', \App\Http\Controllers\Admin\CardTemplate\Show\AdminCardTemplateShowController::class)
            ->name('admin.card-templates.show');
        Route::post('/api/card-templates/{cardTemplate}', \App\Http\Controllers\Admin\CardTemplate\Update\AdminCardTemplateUpdateController::class)
            ->name('admin.card-templates.update');
        Route::delete('/api/card-templates/{cardTemplate}', \App\Http\Controllers\Admin\CardTemplate\Delete\AdminCardTemplateDeleteController::class)
            ->name('admin.card-templates.destroy');
    });

    // ---- Membership Card Patches (batch generation) ----
    // Permissions split into view/create pairs. Row-level filtering is enforced
    // inside each controller via ScopesByMembershipCardCreator.
    // IMPORTANT: static /create route MUST come before the wildcard /{card} route.
    Route::middleware('permission:create membership card patches|create own membership card patches|create partner membership card patches')->group(function () {
        Route::get('/admin/membership-card-patches/create', AdminMembershipCardCreateController::class)->name('admin.membership-card-patches.create');
        Route::post('/admin/membership-card-patches', AdminMembershipCardStoreController::class)->name('admin.membership-card-patches.store');
    });
    Route::middleware('permission:view membership card patches|view own membership card patches|view partner membership card patches')->group(function () {
        Route::get('/admin/membership-card-patches', AdminMembershipCardListController::class)->name('admin.membership-card-patches.list');
        Route::get('/admin/membership-card-patches/{card}', AdminMembershipCardShowController::class)->name('admin.membership-card-patches.show');
    });
    Route::middleware('permission:create membership card patches|create own membership card patches|create partner membership card patches')->group(function () {
        Route::post('/admin/membership-card-patches/{card}/pdf', AdminMembershipCardUploadPdfController::class)->name('admin.membership-card-patches.upload-pdf');
        Route::delete('/admin/membership-card-patches/{card}', AdminMembershipCardDeleteController::class)->name('admin.membership-card-patches.destroy');
    });

    // ---- Membership CRUD (manage memberships, manage own memberships, or manage partner memberships) ----
    // Row-level filtering (creator-scoped or partner-scoped) is enforced inside
    // each controller via ScopesByMembershipCreator.
    Route::middleware('permission:manage memberships|manage own memberships|manage partner memberships')->group(function () {
        // User Membership Management
        Route::get('/admin/user/membership', AdminUserMembershipListController::class)->name('admin.user.membership.list');
        Route::get('/admin/user/membership/create', AdminUserMembershipCreateController::class)->name('admin.user.membership.create');
        Route::post('/admin/user/membership', AdminUserMembershipStoreController::class)->name('admin.user.membership.store');

        // Trash / restore / force-delete / export. Listed before the {user}
        // routes so /admin/user/membership/trash and /export don't get caught
        // by the wildcard. Row-level scoping is enforced inside each
        // controller via ScopesByMembershipCreator so partner/own admins only
        // ever see or act on their own slice.
        Route::get('/admin/user/membership/trash', AdminUserMembershipTrashController::class)->name('admin.user.membership.trash');
        Route::get('/admin/user/membership/export', AdminUserMembershipExportController::class)->name('admin.user.membership.export');
        Route::post('/admin/user/membership/{user}/restore', AdminUserMembershipRestoreController::class)->name('admin.user.membership.restore');
        Route::delete('/admin/user/membership/{user}/force-delete', AdminUserMembershipForceDeleteController::class)->name('admin.user.membership.force-delete');

        Route::get('/admin/user/membership/{user}', AdminUserMembershipShowController::class)->name('admin.user.membership.show');
        Route::get('/admin/user/membership/{user}/edit', AdminUserMembershipEditController::class)->name('admin.user.membership.edit');
        Route::put('/admin/user/membership/{user}', AdminUserMembershipUpdateController::class)->name('admin.user.membership.update');
        Route::put('/admin/user/membership/{user}/password', AdminUserMembershipUpdatePasswordController::class)->name('admin.user.membership.password.update');
        Route::delete('/admin/user/membership/{user}', AdminUserMembershipDeleteController::class)->name('admin.user.membership.destroy');
        Route::get('/admin/user/membership/{user}/logs', AdminUserMembershipLogsController::class)->name('admin.user.membership.logs');
        Route::get('/admin/user/membership/{user}/active-history', AdminUserMembershipActiveHistoryController::class)->name('admin.user.membership.active-history');

        // ---- Active status history (view-only) ----
        Route::get('/admin/active-history', AdminActiveHistoryController::class)
            ->middleware('permission:'.\App\Enums\User\UserPermissionEnum::VIEW_MEMBER_ACTIVE_HISTORIES)
            ->name('admin.active-history.list');

        // Membership number helpers (called from the admin form via axios).
        // Registered under web.php so they share the admin session/auth stack;
        // routes/api.php has no session middleware in Laravel 11 by default.
        Route::get('/api/membership-number/check', [MembershipNumberController::class, 'check'])
            ->name('admin.membership-number.check');
        Route::get('/api/membership-number/generate', [MembershipNumberController::class, 'generateUnique'])
            ->name('admin.membership-number.generate');

        // Card generator (dashboard page) + partner card-layout persistence.
        Route::get('/admin/card-generator', \App\Http\Controllers\Admin\CardGenerator\AdminCardGeneratorController::class)
            ->name('admin.card-generator');
        Route::patch('/api/partners/{partner}/card-layout', \App\Http\Controllers\Api\PartnerCardLayoutController::class)
            ->name('admin.partners.card-layout');
        Route::patch('/api/memberships/{membershipNumber}/card-data', \App\Http\Controllers\Api\MembershipCardDataUpdateController::class)
            ->name('admin.memberships.card-data');
        Route::post('/api/memberships/{membershipNumber}/card-layout', \App\Http\Controllers\Api\CardLayoutController::class)
            ->name('admin.memberships.card-layout');

        // Family Member Management
        Route::post('/admin/user/membership/{user}/{membership}/family-member', AdminFamilyMemberStoreController::class)->name('admin.user.membership.family-member.store');
        Route::put('/admin/user/membership/{user}/{membership}/family-member/{familyMember}', AdminFamilyMemberUpdateController::class)->name('admin.user.membership.family-member.update');
        Route::delete('/admin/user/membership/{user}/{membership}/family-member/{familyMember}', AdminFamilyMemberDeleteController::class)->name('admin.user.membership.family-member.destroy');
    });

    // ---- Membership Usage Management (manage membership usages OR manage own membership usages) ----
    // Gated separately from the memberships area so an admin can be granted
    // usage-only access without also seeing every membership record.
    Route::middleware('permission:manage membership usages|manage own membership usages')->group(function () {
        Route::get('/admin/membership-usage', AdminMembershipUsageListController::class)->name('admin.membership-usage.list');
        Route::get('/admin/membership-usage/create', AdminMembershipUsageCreateController::class)->name('admin.membership-usage.create');
        Route::post('/admin/membership-usage', AdminMembershipUsageStoreController::class)->name('admin.membership-usage.store');
        Route::get('/admin/membership-usage/{membershipUsage}', AdminMembershipUsageShowController::class)->name('admin.membership-usage.show');
        Route::get('/admin/membership-usage/{membershipUsage}/edit', AdminMembershipUsageEditController::class)->name('admin.membership-usage.edit');
        Route::put('/admin/membership-usage/{membershipUsage}', AdminMembershipUsageUpdateController::class)->name('admin.membership-usage.update');
        Route::delete('/admin/membership-usage/{membershipUsage}', AdminMembershipUsageDeleteController::class)->name('admin.membership-usage.destroy');
    });

    // ---- Member Payment Management (manage member payments OR manage own member payments) ----
    Route::middleware('permission:manage member payments|manage own member payments|manage partner member payments')->group(function () {
        // Export/import must come BEFORE /{memberPayment} so static segments don't get caught.
        Route::get('/admin/member-payment/export-to-pay', AdminMemberPaymentExportToPayController::class)->name('admin.member-payment.export-to-pay');
        Route::get('/admin/member-payment/import', AdminMemberPaymentImportPageController::class)->name('admin.member-payment.import.page');
        Route::post('/admin/member-payment/import/preview', AdminMemberPaymentImportPreviewController::class)->name('admin.member-payment.import.preview');
        Route::post('/admin/member-payment/import/commit', AdminMemberPaymentImportCommitController::class)->name('admin.member-payment.import.commit');
        Route::get('/admin/member-payment', AdminMemberPaymentListController::class)->name('admin.member-payment.list');
        Route::get('/admin/member-payment/create', AdminMemberPaymentCreateController::class)->name('admin.member-payment.create');
        Route::post('/admin/member-payment', AdminMemberPaymentStoreController::class)->name('admin.member-payment.store');
        Route::get('/admin/member-payment/{memberPayment}', AdminMemberPaymentShowController::class)->name('admin.member-payment.show');
        Route::get('/admin/member-payment/{memberPayment}/edit', AdminMemberPaymentEditController::class)->name('admin.member-payment.edit');
        Route::put('/admin/member-payment/{memberPayment}', AdminMemberPaymentUpdateController::class)->name('admin.member-payment.update');
        Route::delete('/admin/member-payment/{memberPayment}', AdminMemberPaymentDeleteController::class)->name('admin.member-payment.destroy');
    });

    // ---- Facility management (manage facilities OR manage own facilities) ----
    Route::middleware('permission:manage facilities|manage own facilities')->group(function () {
        Route::get('/admin/facility', AdminFacilityListController::class)->name('admin.facility.list');
        Route::get('/admin/facility/create', AdminFacilityCreateController::class)->name('admin.facility.create');
        Route::post('/admin/facility', AdminFacilityStoreController::class)->name('admin.facility.store');
        // Import/export must come BEFORE /{facility} so /export, /import, etc. don't get caught.
        Route::get('/admin/facility/export', AdminFacilityExportController::class)->name('admin.facility.export');
        Route::get('/admin/facility/import', AdminFacilityImportPageController::class)->name('admin.facility.import.page');
        Route::post('/admin/facility/import/preview', AdminFacilityImportPreviewController::class)->name('admin.facility.import.preview');
        Route::post('/admin/facility/import/commit', AdminFacilityImportCommitController::class)->name('admin.facility.import.commit');
        // Site-to-site migration: a lossless package (data + image files) rather
        // than the human-readable xlsx report above.
        Route::get('/admin/facility/migration', AdminFacilityMigrationPageController::class)->name('admin.facility.migration.page');
        Route::get('/admin/facility/migration/export', AdminFacilityMigrationExportController::class)->name('admin.facility.migration.export');
        Route::get('/admin/facility/migration/export/plan', [AdminFacilityMigrationExportController::class, 'plan'])->name('admin.facility.migration.export.plan');
        Route::get('/admin/facility/migration/template/example', [\App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationTemplateController::class, 'example'])->name('admin.facility.migration.template.example');
        Route::get('/admin/facility/migration/template/blank', [\App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationTemplateController::class, 'blank'])->name('admin.facility.migration.template.blank');
        Route::get('/admin/facility/migration/template/zip/example', [\App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationTemplateController::class, 'zipExample'])->name('admin.facility.migration.template.zip.example');
        Route::get('/admin/facility/migration/template/zip/blank', [\App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationTemplateController::class, 'zipBlank'])->name('admin.facility.migration.template.zip.blank');
        // The restore runs as a session the browser steps through, so neither the
        // request nor the progress bar has to survive the whole package at once.
        Route::post('/admin/facility/migration/inspect', [AdminFacilityMigrationImportController::class, 'inspect'])->name('admin.facility.migration.inspect');
        Route::post('/admin/facility/migration/preview', [AdminFacilityMigrationImportController::class, 'preview'])->name('admin.facility.migration.preview');
        Route::post('/admin/facility/migration/edit', [AdminFacilityMigrationImportController::class, 'edit'])->name('admin.facility.migration.edit');
        Route::post('/admin/facility/migration/begin', [AdminFacilityMigrationImportController::class, 'begin'])->name('admin.facility.migration.begin');
        Route::post('/admin/facility/migration/step', [AdminFacilityMigrationImportController::class, 'step'])->name('admin.facility.migration.step');
        Route::post('/admin/facility/migration/finish', [AdminFacilityMigrationImportController::class, 'finish'])->name('admin.facility.migration.finish');
        Route::post('/admin/facility/migration/cancel', [AdminFacilityMigrationImportController::class, 'cancel'])->name('admin.facility.migration.cancel');
        // AI metadata helper for the form's SEO tab (called via axios, answers JSON).
        Route::post('/admin/facility/seo/generate', AdminFacilitySeoGenerateController::class)->name('admin.facility.seo.generate');
        Route::get('/admin/facility/{facility}', AdminFacilityShowController::class)->name('admin.facility.show');
        Route::get('/admin/facility/{facility}/edit', AdminFacilityEditController::class)->name('admin.facility.edit');
        Route::put('/admin/facility/{facility}', AdminFacilityUpdateController::class)->name('admin.facility.update');
        Route::delete('/admin/facility/{facility}', AdminFacilityDeleteController::class)->name('admin.facility.destroy');
        Route::get('/admin/facility/{facility}/logs', AdminFacilityLogsController::class)->name('admin.facility.logs');
    });

    // ---- Facility branch management (manage facility branches OR own) ----
    Route::middleware('permission:manage facility branches|manage own facility branches')->group(function () {
        Route::get('/admin/facility-branch', AdminFacilityBranchListController::class)->name('admin.facility-branch.list');
        Route::get('/admin/facility-branch/create', AdminFacilityBranchCreateController::class)->name('admin.facility-branch.create');
        Route::post('/admin/facility-branch', AdminFacilityBranchStoreController::class)->name('admin.facility-branch.store');
        // Import/export must come BEFORE /{facilityBranch} so static segments don't get caught.
        Route::get('/admin/facility-branch/export', AdminFacilityBranchExportController::class)->name('admin.facility-branch.export');
        Route::get('/admin/facility-branch/import', AdminFacilityBranchImportPageController::class)->name('admin.facility-branch.import.page');
        Route::post('/admin/facility-branch/import/preview', AdminFacilityBranchImportPreviewController::class)->name('admin.facility-branch.import.preview');
        Route::post('/admin/facility-branch/import/commit', AdminFacilityBranchImportCommitController::class)->name('admin.facility-branch.import.commit');
        Route::get('/admin/facility-branch/{facilityBranch}', AdminFacilityBranchShowController::class)->name('admin.facility-branch.show');
        Route::get('/admin/facility-branch/{facilityBranch}/edit', AdminFacilityBranchEditController::class)->name('admin.facility-branch.edit');
        Route::put('/admin/facility-branch/{facilityBranch}', AdminFacilityBranchUpdateController::class)->name('admin.facility-branch.update');
        Route::delete('/admin/facility-branch/{facilityBranch}', AdminFacilityBranchDeleteController::class)->name('admin.facility-branch.destroy');
        Route::get('/admin/facility-branch/{facilityBranch}/logs', AdminFacilityBranchLogsController::class)->name('admin.facility-branch.logs');
    });

    // ---- Governorate (manage governorates OR own) ----
    Route::middleware('permission:manage governorates|manage own governorates')->group(function () {
        Route::get('/admin/governorate', AdminGovernorateListController::class)->name('admin.governorate.list');
        Route::get('/admin/governorate/create', AdminGovernorateCreateController::class)->name('admin.governorate.create');
        Route::post('/admin/governorate', AdminGovernorateStoreController::class)->name('admin.governorate.store');
        Route::get('/admin/governorate/{governorate}', AdminGovernorateShowController::class)->name('admin.governorate.show');
        Route::get('/admin/governorate/{governorate}/edit', AdminGovernorateEditController::class)->name('admin.governorate.edit');
        Route::put('/admin/governorate/{governorate}', AdminGovernorateUpdateController::class)->name('admin.governorate.update');
        Route::delete('/admin/governorate/{governorate}', AdminGovernorateDeleteController::class)->name('admin.governorate.destroy');
    });

    // ---- FacilityType (manage facilities — same perm as facilities) ----
    Route::middleware('permission:manage facilities|manage own facilities')->group(function () {
        Route::get('/admin/facility-type', AdminFacilityTypeListController::class)->name('admin.facility-type.list');
        Route::get('/admin/facility-type/create', AdminFacilityTypeCreateController::class)->name('admin.facility-type.create');
        Route::post('/admin/facility-type', AdminFacilityTypeStoreController::class)->name('admin.facility-type.store');
        Route::get('/admin/facility-type/{facilityType}', AdminFacilityTypeShowController::class)->name('admin.facility-type.show');
        Route::get('/admin/facility-type/{facilityType}/edit', AdminFacilityTypeEditController::class)->name('admin.facility-type.edit');
        Route::put('/admin/facility-type/{facilityType}', AdminFacilityTypeUpdateController::class)->name('admin.facility-type.update');
        Route::delete('/admin/facility-type/{facilityType}', AdminFacilityTypeDeleteController::class)->name('admin.facility-type.destroy');
    });

    // ---- Offer (manage offers OR own) ----
    Route::middleware('permission:manage offers|manage own offers')->group(function () {
        Route::get('/admin/offer', AdminOfferListController::class)->name('admin.offer.list');
        Route::get('/admin/offer/create', AdminOfferCreateController::class)->name('admin.offer.create');
        Route::post('/admin/offer', AdminOfferStoreController::class)->name('admin.offer.store');
        Route::get('/admin/offer/{offer}', AdminOfferShowController::class)->name('admin.offer.show');
        Route::get('/admin/offer/{offer}/edit', AdminOfferEditController::class)->name('admin.offer.edit');
        Route::put('/admin/offer/{offer}', AdminOfferUpdateController::class)->name('admin.offer.update');
        Route::delete('/admin/offer/{offer}', AdminOfferDeleteController::class)->name('admin.offer.destroy');
    });

    // ---- Contract (manage contracts OR own) ----
    Route::middleware('permission:manage contracts|manage own contracts')->group(function () {
        Route::get('/admin/contract', AdminContractListController::class)->name('admin.contract.list');
        Route::get('/admin/contract/create', AdminContractCreateController::class)->name('admin.contract.create');
        Route::post('/admin/contract', AdminContractStoreController::class)->name('admin.contract.store');
        Route::get('/admin/contract/{contract}', AdminContractShowController::class)->name('admin.contract.show');
        Route::get('/admin/contract/{contract}/edit', AdminContractEditController::class)->name('admin.contract.edit');
        Route::put('/admin/contract/{contract}', AdminContractUpdateController::class)->name('admin.contract.update');
        Route::delete('/admin/contract/{contract}', AdminContractDeleteController::class)->name('admin.contract.destroy');
    });

    // ---- FAQ (manage faqs OR own) ----
    Route::middleware('permission:manage faqs|manage own faqs')->group(function () {
        Route::get('/admin/faq', AdminFaqListController::class)->name('admin.faq.list');
        Route::get('/admin/faq/create', AdminFaqCreateController::class)->name('admin.faq.create');
        Route::post('/admin/faq', AdminFaqStoreController::class)->name('admin.faq.store');
        Route::get('/admin/faq/{faq}/edit', AdminFaqEditController::class)->name('admin.faq.edit');
        Route::put('/admin/faq/{faq}', AdminFaqUpdateController::class)->name('admin.faq.update');
        Route::delete('/admin/faq/{faq}', AdminFaqDeleteController::class)->name('admin.faq.destroy');
    });

    // ---- News Ticker (manage news tickers OR own) ----
    Route::middleware('permission:manage news tickers|manage own news tickers')->group(function () {
        Route::get('/admin/news-ticker', AdminNewsTickerListController::class)->name('admin.news-ticker.list');
        Route::get('/admin/news-ticker/create', AdminNewsTickerCreateController::class)->name('admin.news-ticker.create');
        Route::post('/admin/news-ticker', AdminNewsTickerStoreController::class)->name('admin.news-ticker.store');
        Route::get('/admin/news-ticker/{newsTicker}/edit', AdminNewsTickerEditController::class)->name('admin.news-ticker.edit');
        Route::put('/admin/news-ticker/{newsTicker}', AdminNewsTickerUpdateController::class)->name('admin.news-ticker.update');
        Route::delete('/admin/news-ticker/{newsTicker}', AdminNewsTickerDeleteController::class)->name('admin.news-ticker.destroy');
    });

    // ---- Partner (manage partners OR own) ----
    Route::middleware('permission:manage partners|manage own partners')->group(function () {
        Route::get('/admin/partner', AdminPartnerListController::class)->name('admin.partner.list');
        Route::get('/admin/partner/create', AdminPartnerCreateController::class)->name('admin.partner.create');
        Route::post('/admin/partner', AdminPartnerStoreController::class)->name('admin.partner.store');
        Route::get('/admin/partner/{partner}/edit', AdminPartnerEditController::class)->name('admin.partner.edit');
        Route::put('/admin/partner/{partner}', AdminPartnerUpdateController::class)->name('admin.partner.update');
        Route::delete('/admin/partner/{partner}', AdminPartnerDeleteController::class)->name('admin.partner.destroy');
    });

    // ---- Partner Offer (manage partner offers OR own) ----
    Route::middleware('permission:manage partner offers|manage own partner offers')->group(function () {
        Route::get('/admin/partner-offer', AdminPartnerOfferListController::class)->name('admin.partner-offer.list');
        Route::get('/admin/partner-offer/trash', AdminPartnerOfferTrashController::class)->name('admin.partner-offer.trash');
        Route::get('/admin/partner-offer/create', AdminPartnerOfferCreateController::class)->name('admin.partner-offer.create');
        Route::post('/admin/partner-offer', AdminPartnerOfferStoreController::class)->name('admin.partner-offer.store');
        Route::get('/admin/partner-offer/{partnerOffer}/edit', AdminPartnerOfferEditController::class)->name('admin.partner-offer.edit');
        Route::put('/admin/partner-offer/{partnerOffer}', AdminPartnerOfferUpdateController::class)->name('admin.partner-offer.update');
        Route::delete('/admin/partner-offer/{partnerOffer}', AdminPartnerOfferDeleteController::class)->name('admin.partner-offer.destroy');
        Route::post('/admin/partner-offer/{partnerOffer}/restore', AdminPartnerOfferRestoreController::class)->name('admin.partner-offer.restore');
        Route::delete('/admin/partner-offer/{partnerOffer}/force-delete', AdminPartnerOfferForceDeleteController::class)->name('admin.partner-offer.force-delete');

        // Partner Offer Requests (list, show, delete)
        Route::get('/admin/partner-offer-request', AdminPartnerOfferRequestListController::class)->name('admin.partner-offer-request.list');
        Route::get('/admin/partner-offer-request/{partnerOfferRequest}', AdminPartnerOfferRequestShowController::class)->name('admin.partner-offer-request.show');
        Route::delete('/admin/partner-offer-request/{partnerOfferRequest}', AdminPartnerOfferRequestDeleteController::class)->name('admin.partner-offer-request.destroy');
    });

    // ---- Service (manage services OR own) ----
    Route::middleware('permission:manage services|manage own services')->group(function () {
        Route::get('/admin/service', AdminServiceListController::class)->name('admin.service.list');
        Route::get('/admin/service/create', AdminServiceCreateController::class)->name('admin.service.create');
        Route::post('/admin/service', AdminServiceStoreController::class)->name('admin.service.store');
        Route::get('/admin/service/{service}', AdminServiceShowController::class)->name('admin.service.show');
        Route::get('/admin/service/{service}/edit', AdminServiceEditController::class)->name('admin.service.edit');
        Route::put('/admin/service/{service}', AdminServiceUpdateController::class)->name('admin.service.update');
        Route::delete('/admin/service/{service}', AdminServiceDeleteController::class)->name('admin.service.destroy');
    });

    // ---- Service Type / Category (manage services OR own) ----
    Route::middleware('permission:manage services|manage own services')->group(function () {
        Route::get('/admin/service-type', AdminServiceTypeListController::class)->name('admin.service-type.list');
        Route::get('/admin/service-type/create', AdminServiceTypeCreateController::class)->name('admin.service-type.create');
        Route::post('/admin/service-type', AdminServiceTypeStoreController::class)->name('admin.service-type.store');
        Route::get('/admin/service-type/{serviceType}', AdminServiceTypeShowController::class)->name('admin.service-type.show');
        Route::get('/admin/service-type/{serviceType}/edit', AdminServiceTypeEditController::class)->name('admin.service-type.edit');
        Route::put('/admin/service-type/{serviceType}', AdminServiceTypeUpdateController::class)->name('admin.service-type.update');
        Route::delete('/admin/service-type/{serviceType}', AdminServiceTypeDeleteController::class)->name('admin.service-type.destroy');
    });

    // ---- Tag (manage services OR own) ----
    Route::middleware('permission:manage services|manage own services')->group(function () {
        Route::get('/admin/tag', AdminTagListController::class)->name('admin.tag.list');
        Route::get('/admin/tag/create', AdminTagCreateController::class)->name('admin.tag.create');
        Route::post('/admin/tag', AdminTagStoreController::class)->name('admin.tag.store');
        Route::get('/admin/tag/{tag}', AdminTagShowController::class)->name('admin.tag.show');
        Route::get('/admin/tag/{tag}/edit', AdminTagEditController::class)->name('admin.tag.edit');
        Route::put('/admin/tag/{tag}', AdminTagUpdateController::class)->name('admin.tag.update');
        Route::delete('/admin/tag/{tag}', AdminTagDeleteController::class)->name('admin.tag.destroy');
    });

    // ---- Sales (manage sales OR own) ----
    Route::middleware('permission:manage sales|manage own sales')->group(function () {
        Route::get('/admin/sales/export', AdminSalesExportController::class)->name('admin.sales.export');
        Route::get('/admin/sales/import/template', AdminSalesImportTemplateController::class)->name('admin.sales.import.template');
        Route::get('/admin/sales/import', AdminSalesImportPageController::class)->name('admin.sales.import.page');
        Route::post('/admin/sales/import/preview', AdminSalesImportPreviewController::class)->name('admin.sales.import.preview');
        Route::post('/admin/sales/import/commit', AdminSalesImportCommitController::class)->name('admin.sales.import.commit');
        Route::get('/admin/sales', AdminSalesListController::class)->name('admin.sales.list');
        Route::get('/admin/sales/create', AdminSalesCreateController::class)->name('admin.sales.create');
        Route::post('/admin/sales', AdminSalesStoreController::class)->name('admin.sales.store');
        Route::get('/admin/sales/{sale}/edit', AdminSalesEditController::class)->name('admin.sales.edit');
        Route::put('/admin/sales/{sale}', AdminSalesUpdateController::class)->name('admin.sales.update');
        Route::delete('/admin/sales/{sale}', AdminSalesDeleteController::class)->name('admin.sales.destroy');
    });

    // ---- Company (manage companies OR own) ----
    Route::middleware('permission:manage companies|manage own companies')->group(function () {
        Route::get('/admin/company', AdminCompanyListController::class)->name('admin.company.list');
        Route::get('/admin/company/export', AdminCompanyExportController::class)->name('admin.company.export');
        Route::get('/admin/company/import', AdminCompanyImportPageController::class)->name('admin.company.import');
        Route::get('/admin/company/import/template', AdminCompanyImportTemplateController::class)->name('admin.company.import.template');
        Route::post('/admin/company/import/preview', AdminCompanyImportPreviewController::class)->name('admin.company.import.preview');
        Route::post('/admin/company/import', AdminCompanyImportCommitController::class)->name('admin.company.import.run');
        Route::get('/admin/company/create', AdminCompanyCreateController::class)->name('admin.company.create');
        Route::post('/admin/company', AdminCompanyStoreController::class)->name('admin.company.store');
        Route::get('/admin/company/{company}/edit', AdminCompanyEditController::class)->name('admin.company.edit');
        Route::put('/admin/company/{company}', AdminCompanyUpdateController::class)->name('admin.company.update');
        Route::delete('/admin/company/{company}', AdminCompanyDeleteController::class)->name('admin.company.destroy');
        Route::get('/admin/company/{company}/members', AdminCompanyMembersController::class)->name('admin.company.members');
        Route::get('/admin/company/{company}/members/export', AdminCompanyMembersExportController::class)->name('admin.company.members.export');
    });

    // ---- Client error logs (super_admin only) ----
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/client-error-logs', [\App\Http\Controllers\Admin\ClientErrorLogController::class, 'index'])->name('admin.client-error-logs.index');
        Route::delete('/admin/client-error-logs/{clientErrorLog}', [\App\Http\Controllers\Admin\ClientErrorLogController::class, 'destroy'])->name('admin.client-error-logs.destroy');
    });

    // ---- Admin user management (permission-based) ----
    Route::middleware('permission:manage users|manage admin users')->group(function () {
        Route::get('/admin/admin-users', \App\Http\Controllers\Admin\AdminUser\Index\AdminUserIndexController::class)->name('admin.admin-users.index');
        Route::get('/admin/admin-users/create', \App\Http\Controllers\Admin\AdminUser\Create\AdminUserCreateController::class)->name('admin.admin-users.create');
        Route::post('/admin/admin-users', \App\Http\Controllers\Admin\AdminUser\Store\AdminUserStoreController::class)->name('admin.admin-users.store');
        Route::get('/admin/admin-users/{adminUser}', \App\Http\Controllers\Admin\AdminUser\Show\AdminUserShowController::class)->name('admin.admin-users.show');
        Route::get('/admin/admin-users/{adminUser}/edit', \App\Http\Controllers\Admin\AdminUser\Edit\AdminUserEditController::class)->name('admin.admin-users.edit');
        Route::put('/admin/admin-users/{adminUser}', \App\Http\Controllers\Admin\AdminUser\Update\AdminUserUpdateController::class)->name('admin.admin-users.update');
        Route::delete('/admin/admin-users/{adminUser}', \App\Http\Controllers\Admin\AdminUser\Delete\AdminUserDeleteController::class)->name('admin.admin-users.destroy');
    });

    // ---- Role management (permission-based) ----
    Route::middleware('permission:manage roles')->group(function () {
        Route::get('/admin/roles', \App\Http\Controllers\Admin\Role\Index\AdminRoleIndexController::class)->name('admin.roles.index');
        Route::get('/admin/roles/create', \App\Http\Controllers\Admin\Role\Create\AdminRoleCreateController::class)->name('admin.roles.create');
        Route::post('/admin/roles', \App\Http\Controllers\Admin\Role\Store\AdminRoleStoreController::class)->name('admin.roles.store');
        Route::get('/admin/roles/{role}/edit', \App\Http\Controllers\Admin\Role\Edit\AdminRoleEditController::class)->name('admin.roles.edit');
        Route::put('/admin/roles/{role}', \App\Http\Controllers\Admin\Role\Update\AdminRoleUpdateController::class)->name('admin.roles.update');
        Route::delete('/admin/roles/{role}', \App\Http\Controllers\Admin\Role\Delete\AdminRoleDeleteController::class)->name('admin.roles.destroy');
    });
});

// Temporary DB backup download — remove after use
Route::get('/download-db-backup', function () {
    $file = storage_path('app/ashhealthcare_backup_20260521_232602.sql');

    return response()->download($file, 'ashhealthcare_backup_20260521_232602.sql');
})->name('db.backup.download');
