<?php

namespace App\Enums\User;

/**
 * Each resource has up to three permissions:
 *   - `manage X`     → full CRUD on every record
 *   - `manage own X` → CRUD only on records the admin created themselves
 *   - `view X`       → read-only: the list and detail screens, nothing else.
 *                      No create/edit/delete, and no export or import either,
 *                      since both move data in or out of the system.
 *
 * `view X` is not creator-scoped: a read-only account sees every row, because
 * withholding rows from someone who cannot change them buys nothing.
 *
 * Row-level scoping for the "own" variant is enforced inside the controllers
 * that read the relevant `created_by` column. Today, only the membership area
 * has that wiring; the other "own *" permissions are declared so they appear
 * in /admin/roles/create but they will not restrict access until the matching
 * controllers/migrations are landed.
 */
enum UserPermissionEnum
{
    public const MANAGE_USERS = 'manage users';

    public const MANAGE_OWN_USERS = 'manage own users';

    public const MANAGE_ADMIN_USERS = 'manage admin users';

    public const MANAGE_OWN_ADMIN_USERS = 'manage own admin users';

    public const MANAGE_ROLES = 'manage roles';

    public const MANAGE_OWN_ROLES = 'manage own roles';

    public const MANAGE_MEMBERSHIPS = 'manage memberships';

    public const MANAGE_OWN_MEMBERSHIPS = 'manage own memberships';

    public const VIEW_MEMBERSHIP_CARD_PATCHES = 'view membership card patches';

    public const CREATE_MEMBERSHIP_CARD_PATCHES = 'create membership card patches';

    public const VIEW_OWN_MEMBERSHIP_CARD_PATCHES = 'view own membership card patches';

    public const CREATE_OWN_MEMBERSHIP_CARD_PATCHES = 'create own membership card patches';

    /**
     * Standalone permission: manage the reusable card designs in
     * `card_templates`. Global design assets rather than per-admin records, so
     * there is no creator-scoped "own" counterpart.
     */
    public const MANAGE_CARD_TEMPLATES = 'manage card templates';

    public const MANAGE_MEMBERSHIP_USAGES = 'manage membership usages';

    public const MANAGE_OWN_MEMBERSHIP_USAGES = 'manage own membership usages';

    public const MANAGE_OFFERS = 'manage offers';

    public const MANAGE_OWN_OFFERS = 'manage own offers';

    public const MANAGE_SERVICES = 'manage services';

    public const MANAGE_OWN_SERVICES = 'manage own services';

    public const MANAGE_CONTRACTS = 'manage contracts';

    public const MANAGE_OWN_CONTRACTS = 'manage own contracts';

    public const MANAGE_COMPANIES = 'manage companies';

    public const MANAGE_OWN_COMPANIES = 'manage own companies';

    public const MANAGE_FACILITIES = 'manage facilities';

    public const MANAGE_OWN_FACILITIES = 'manage own facilities';

    public const MANAGE_PRODUCT_TYPES = 'manage product types';

    public const MANAGE_OWN_PRODUCT_TYPES = 'manage own product types';

    public const MANAGE_PRODUCTS = 'manage products';

    public const MANAGE_OWN_PRODUCTS = 'manage own products';

    public const MANAGE_ORDERS = 'manage orders';

    public const MANAGE_OWN_ORDERS = 'manage own orders';

    public const MANAGE_FACILITY_BRANCHES = 'manage facility branches';

    public const MANAGE_OWN_FACILITY_BRANCHES = 'manage own facility branches';

    public const MANAGE_GOVERNORATES = 'manage governorates';

    public const MANAGE_OWN_GOVERNORATES = 'manage own governorates';

    public const MANAGE_CONTACT_MESSAGES = 'manage contact messages';

    public const MANAGE_OWN_CONTACT_MESSAGES = 'manage own contact messages';

    public const MANAGE_FAQS = 'manage faqs';

    public const MANAGE_OWN_FAQS = 'manage own faqs';

    public const MANAGE_PARTNERS = 'manage partners';

    public const MANAGE_OWN_PARTNERS = 'manage own partners';

    public const MANAGE_PARTNER_OFFERS = 'manage partner offers';

    public const MANAGE_OWN_PARTNER_OFFERS = 'manage own partner offers';

    public const MANAGE_SALES = 'manage sales';

    public const MANAGE_OWN_SALES = 'manage own sales';

    public const MANAGE_NEWS_TICKERS = 'manage news tickers';

    public const MANAGE_OWN_NEWS_TICKERS = 'manage own news tickers';

    public const MANAGE_MEMBER_PAYMENTS = 'manage member payments';

    public const MANAGE_OWN_MEMBER_PAYMENTS = 'manage own member payments';

    /**
     * Standalone permission: lets an admin manage memberships scoped to the
     * partner attached to their own user row (User::partner_id). Not paired
     * with an "own" variant because the scoping dimension is partner, not
     * creator.
     */
    public const MANAGE_PARTNER_MEMBERSHIPS = 'manage partner memberships';

    /**
     * Standalone permission: like MANAGE_PARTNER_MEMBERSHIPS but for the
     * member-payments area. Scopes a payment to memberships whose partner_id
     * matches the admin's own User::partner_id.
     */
    public const MANAGE_PARTNER_MEMBER_PAYMENTS = 'manage partner member payments';

    public const VIEW_PARTNER_MEMBERSHIP_CARD_PATCHES = 'view partner membership card patches';

    public const CREATE_PARTNER_MEMBERSHIP_CARD_PATCHES = 'create partner membership card patches';

    /**
     * Standalone permission gating access to Jetstream's profile area
     * (`/user/profile`). Personal — every authenticated user typically holds
     * it. Without it the sidebar Profile link is hidden and the route
     * middleware blocks navigation.
     */
    public const MANAGE_PROFILE = 'manage profile';

    /**
     * Standalone permission: view the active status change history for members.
     * Read-only access to the member_active_histories table.
     */
    public const VIEW_MEMBER_ACTIVE_HISTORIES = 'view member active histories';

    /**
     * Read-only counterparts to the `manage X` permissions above. Held by the
     * `viewer` role, and grantable on their own to give an account a look at
     * one area without the ability to change anything in it.
     *
     * `view membership card patches` and `view member active histories` are
     * declared further up: those two areas were read-only from the start.
     */
    public const VIEW_USERS = 'view users';

    public const VIEW_ADMIN_USERS = 'view admin users';

    public const VIEW_ROLES = 'view roles';

    public const VIEW_MEMBERSHIPS = 'view memberships';

    public const VIEW_MEMBERSHIP_USAGES = 'view membership usages';

    public const VIEW_MEMBER_PAYMENTS = 'view member payments';

    public const VIEW_CARD_TEMPLATES = 'view card templates';

    public const VIEW_OFFERS = 'view offers';

    public const VIEW_SERVICES = 'view services';

    public const VIEW_CONTRACTS = 'view contracts';

    public const VIEW_COMPANIES = 'view companies';

    public const VIEW_FACILITIES = 'view facilities';

    public const VIEW_FACILITY_BRANCHES = 'view facility branches';

    public const VIEW_PRODUCT_TYPES = 'view product types';

    public const VIEW_PRODUCTS = 'view products';

    public const VIEW_ORDERS = 'view orders';

    public const VIEW_GOVERNORATES = 'view governorates';

    public const VIEW_CONTACT_MESSAGES = 'view contact messages';

    public const VIEW_FAQS = 'view faqs';

    public const VIEW_PARTNERS = 'view partners';

    public const VIEW_PARTNER_OFFERS = 'view partner offers';

    public const VIEW_SALES = 'view sales';

    public const VIEW_NEWS_TICKERS = 'view news tickers';

    /**
     * Permissions that do not have a paired full/own counterpart. Kept
     * separate from pairs() so they don't get listed twice or rejected by
     * the pair-conflict validator.
     */
    public static function standalone(): array
    {
        return [
            self::MANAGE_PARTNER_MEMBERSHIPS,
            self::MANAGE_PARTNER_MEMBER_PAYMENTS,
            self::VIEW_PARTNER_MEMBERSHIP_CARD_PATCHES,
            self::CREATE_PARTNER_MEMBERSHIP_CARD_PATCHES,
            self::MANAGE_CARD_TEMPLATES,
            /*
             * Standalone rather than paired: a contact enquiry arrives from a
             * public form and has no creator (`created_by` is null on every
             * one), so a "manage own contact messages" permission could never
             * grant anybody anything. It was offered on the role screen and
             * enforced nowhere; only super_admin ever held it.
             */
            self::MANAGE_CONTACT_MESSAGES,
            self::MANAGE_PROFILE,
            self::VIEW_MEMBER_ACTIVE_HISTORIES,
        ];
    }

    /**
     * Resources that pair a full permission with a creator-scoped one.
     * Order here drives the order shown on /admin/roles/create.
     */
    public static function pairs(): array
    {
        return [
            [self::MANAGE_USERS, self::MANAGE_OWN_USERS],
            [self::MANAGE_ADMIN_USERS, self::MANAGE_OWN_ADMIN_USERS],
            [self::MANAGE_ROLES, self::MANAGE_OWN_ROLES],
            [self::MANAGE_MEMBERSHIPS, self::MANAGE_OWN_MEMBERSHIPS],
            [self::VIEW_MEMBERSHIP_CARD_PATCHES, self::VIEW_OWN_MEMBERSHIP_CARD_PATCHES],
            [self::CREATE_MEMBERSHIP_CARD_PATCHES, self::CREATE_OWN_MEMBERSHIP_CARD_PATCHES],
            [self::MANAGE_MEMBERSHIP_USAGES, self::MANAGE_OWN_MEMBERSHIP_USAGES],
            [self::MANAGE_OFFERS, self::MANAGE_OWN_OFFERS],
            [self::MANAGE_CONTRACTS, self::MANAGE_OWN_CONTRACTS],
            [self::MANAGE_COMPANIES, self::MANAGE_OWN_COMPANIES],
            [self::MANAGE_FACILITIES, self::MANAGE_OWN_FACILITIES],
            [self::MANAGE_FACILITY_BRANCHES, self::MANAGE_OWN_FACILITY_BRANCHES],
            [self::MANAGE_PRODUCT_TYPES, self::MANAGE_OWN_PRODUCT_TYPES],
            [self::MANAGE_PRODUCTS, self::MANAGE_OWN_PRODUCTS],
            [self::MANAGE_ORDERS, self::MANAGE_OWN_ORDERS],
            [self::MANAGE_GOVERNORATES, self::MANAGE_OWN_GOVERNORATES],
            [self::MANAGE_FAQS, self::MANAGE_OWN_FAQS],
            [self::MANAGE_PARTNERS, self::MANAGE_OWN_PARTNERS],
            [self::MANAGE_PARTNER_OFFERS, self::MANAGE_OWN_PARTNER_OFFERS],
            [self::MANAGE_SALES, self::MANAGE_OWN_SALES],
            [self::MANAGE_SERVICES, self::MANAGE_OWN_SERVICES],
            [self::MANAGE_NEWS_TICKERS, self::MANAGE_OWN_NEWS_TICKERS],
            [self::MANAGE_MEMBER_PAYMENTS, self::MANAGE_OWN_MEMBER_PAYMENTS],
        ];
    }

    /**
     * The read-only permissions introduced alongside the `viewer` role, in the
     * order they should appear on /admin/roles/create. Excludes the two view
     * permissions declared in pairs()/standalone(), which are listed there.
     */
    public static function viewOnly(): array
    {
        return [
            self::VIEW_USERS,
            self::VIEW_ADMIN_USERS,
            self::VIEW_ROLES,
            self::VIEW_MEMBERSHIPS,
            self::VIEW_MEMBERSHIP_USAGES,
            self::VIEW_MEMBER_PAYMENTS,
            self::VIEW_CARD_TEMPLATES,
            self::VIEW_OFFERS,
            self::VIEW_CONTRACTS,
            self::VIEW_COMPANIES,
            self::VIEW_FACILITIES,
            self::VIEW_FACILITY_BRANCHES,
            self::VIEW_GOVERNORATES,
            self::VIEW_PRODUCT_TYPES,
            self::VIEW_PRODUCTS,
            self::VIEW_ORDERS,
            self::VIEW_SERVICES,
            self::VIEW_CONTACT_MESSAGES,
            self::VIEW_FAQS,
            self::VIEW_PARTNERS,
            self::VIEW_PARTNER_OFFERS,
            self::VIEW_SALES,
            self::VIEW_NEWS_TICKERS,
        ];
    }

    /**
     * Everything the `viewer` role holds: every read-only permission, plus the
     * two areas that were already view-only, plus the personal profile page.
     *
     * Deliberately excludes `view own membership card patches` and the partner
     * variants — a global viewer is not scoped to a creator or a partner.
     */
    public static function readOnlyAccess(): array
    {
        return array_values(array_unique(array_merge(
            self::viewOnly(),
            [
                self::VIEW_MEMBERSHIP_CARD_PATCHES,
                self::VIEW_MEMBER_ACTIVE_HISTORIES,
                self::MANAGE_PROFILE,
            ],
        )));
    }

    public static function all(): array
    {
        $out = [];
        foreach (self::pairs() as [$full, $own]) {
            $out[] = $full;
            $out[] = $own;
        }
        foreach (self::standalone() as $perm) {
            $out[] = $perm;
        }
        foreach (self::viewOnly() as $perm) {
            $out[] = $perm;
        }

        return array_values(array_unique($out));
    }

    /**
     * Permissions reserved for the super admin role only.
     */
    public static function superAdminOnly(): array
    {
        return [];
    }

    public static function contentManagement(): array
    {
        return array_values(array_diff(self::all(), self::superAdminOnly()));
    }

    public static function editorPermissions(): array
    {
        return [
            self::MANAGE_MEMBERSHIPS,
            self::MANAGE_FACILITIES,
            self::MANAGE_FACILITY_BRANCHES,
            self::MANAGE_NEWS_TICKERS,
            self::MANAGE_OWN_NEWS_TICKERS,
            self::MANAGE_PARTNER_OFFERS,
            self::MANAGE_OWN_PARTNER_OFFERS,
        ];
    }

    /**
     * Permissions that grant access to the membership area. A user with either
     * of these can enter membership routes; row-level scoping (own vs all)
     * happens inside the controllers.
     */
    public static function membershipAccess(): array
    {
        return [
            self::MANAGE_MEMBERSHIPS,
            self::MANAGE_OWN_MEMBERSHIPS,
            self::MANAGE_PARTNER_MEMBERSHIPS,
        ];
    }

    /**
     * Return the resource label (e.g. "partners") if the given permission has
     * a paired full/own counterpart, or null otherwise. Used by validators to
     * reject roles that hold both `manage X` and `manage own X` at once.
     */
    public static function resourceFor(string $permission): ?string
    {
        foreach (self::pairs() as [$full, $own]) {
            if ($permission === $full || $permission === $own) {
                $resource = trim(str_replace(['view own ', 'create own ', 'manage own ', 'view ', 'create ', 'manage '], '', $full));

                return $resource;
            }
        }

        return null;
    }
}
