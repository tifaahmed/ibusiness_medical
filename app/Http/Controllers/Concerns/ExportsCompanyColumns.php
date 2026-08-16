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
     * punctuation removed, Arabic diacritics/hamza variants folded).
     * Both English and Arabic labels are accepted, since the export writes the
     * labels in whatever locale the admin UI is currently in.
     */
    protected function companyColumnAliases(): array
    {
        return [
            'id'               => ['id', 'المعرّف'],
            'name_en'          => ['nameenglish', 'nameen', 'name', 'الاسم (إنجليزي)', 'الاسم (انجليزي)', 'الاسم'],
            'name_ar'          => ['namearabic', 'namear', 'arabicname', 'الاسم (عربي)', 'الاسم (العربية)', 'اسم عربي'],
            'slug'             => ['slug', 'الرابط المختصر', 'الرابط'],
            'created_by_email' => ['createdbyemail', 'createdby', 'creator', 'creatorname', 'المنشئ (البريد الإلكتروني)', 'المنشئ', 'البريد الإلكتروني'],
            'created_at'       => ['createdat', 'createdate', 'creationdate', 'تاريخ الإنشاء'],
            'updated_at'       => ['updatedat', 'updatedate', 'تاريخ التحديث'],
        ];
    }

    /**
     * Normalize an Excel header cell so it can be matched against the aliases
     * above regardless of case, spaces, parentheses or other punctuation.
     * Arabic text is kept, with diacritics stripped and the common hamza/alef
     * and ta-marbuta variants folded onto their base letter.
     */
    protected function normalizeHeader(string $header): string
    {
        $s = mb_strtolower(trim($header));
        $s = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ؤ', 'ئ', 'ة'],
            ['ا', 'ا', 'ا', 'ا', 'و', 'ي', 'ه'],
            $s
        );
        $s = (string) preg_replace('/[\x{064B}-\x{0655}\x{0670}]/u', '', $s);

        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', $s);
    }
}
