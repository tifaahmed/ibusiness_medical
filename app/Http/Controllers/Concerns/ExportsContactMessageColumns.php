<?php

namespace App\Http\Controllers\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Column catalogue for the contact-message XLSX export. This is export-only
 * (there is no import round-trip for enquiries), so unlike the Company
 * columns there are no header aliases to parse back.
 */
trait ExportsContactMessageColumns
{
    protected function contactMessageColumnDefinitions(): array
    {
        return [
            'id'                  => ['label' => __('admin.contact_message_export.col_id'), 'width' => 10, 'align' => Alignment::HORIZONTAL_CENTER],
            'name'                => ['label' => __('admin.contact_message_export.col_name'), 'width' => 28],
            'email'               => ['label' => __('admin.contact_message_export.col_email'), 'width' => 30],
            'phone'               => ['label' => __('admin.contact_message_export.col_phone'), 'width' => 20],
            'commercial_register' => ['label' => __('admin.contact_message_export.col_commercial_register'), 'width' => 22],
            'source'              => ['label' => __('admin.contact_message_export.col_source'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER],
            'status'              => ['label' => __('admin.contact_message_export.col_status'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER],
            'sales_name'          => ['label' => __('admin.contact_message_export.col_sales_name'), 'width' => 24],
            'subject'             => ['label' => __('admin.contact_message_export.col_subject'), 'width' => 34],
            'message'             => ['label' => __('admin.contact_message_export.col_message'), 'width' => 50],
            'admin_notes'         => ['label' => __('admin.contact_message_export.col_admin_notes'), 'width' => 40],
            'created_at'          => ['label' => __('admin.contact_message_export.col_created_at'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'read_at'             => ['label' => __('admin.contact_message_export.col_read_at'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'replied_at'          => ['label' => __('admin.contact_message_export.col_replied_at'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
        ];
    }
}
