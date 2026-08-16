<?php

namespace App\Http\Controllers\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Shared column catalogue for the Company XLSX export / import round-trip.
 *
 * The export, the downloadable template/example and the import parser all read
 * from this single definition so the columns never drift apart: whatever you
 * export can be edited and imported straight back (including the id).
 */
trait ExportsCompanyColumns
{
    /**
     * Column key => display + width used when building Excel files.
     */
    protected function companyColumnDefinitions(): array
    {
        return [
            'id'               => ['label' => __('admin.company_export.col_id'), 'width' => 10, 'align' => Alignment::HORIZONTAL_CENTER],
            'name_en'          => ['label' => __('admin.company_export.col_name_en'), 'width' => 44],
            'name_ar'          => ['label' => __('admin.company_export.col_name_ar'), 'width' => 44],
            'slug'             => ['label' => __('admin.company_export.col_slug'), 'width' => 32],
            'created_by_email' => ['label' => __('admin.company_export.col_created_by_email'), 'width' => 34],
            'created_at'       => ['label' => __('admin.company_export.col_created_at'), 'width' => 24, 'align' => Alignment::HORIZONTAL_CENTER],
            'updated_at'       => ['label' => __('admin.company_export.col_updated_at'), 'width' => 24, 'align' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    /**
     * Accepted synonyms for each column header when parsing an uploaded file.
     * Values are matched against the normalized header (lowercased, all
     * non-alphanumeric characters removed).
     */
    protected function companyColumnAliases(): array
    {
        return [
            'id'               => ['id'],
            'name_en'          => ['nameenglish', 'nameen', 'name'],
            'name_ar'          => ['namearabic', 'namear', 'arabicname'],
            'slug'             => ['slug'],
            'created_by_email' => ['createdbyemail', 'createdby', 'creator', 'creatorname'],
            'created_at'       => ['createdat', 'createdate', 'creationdate'],
            'updated_at'       => ['updatedat', 'updatedate'],
        ];
    }

    /**
     * Normalize an Excel header cell so it can be matched against the aliases
     * above regardless of case, spaces, parentheses or other punctuation.
     */
    protected function normalizeHeader(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($header)));
    }
}
