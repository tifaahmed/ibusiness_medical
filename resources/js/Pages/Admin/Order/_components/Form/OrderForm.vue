<template>
  <div class="space-y-3">
    <!-- Status: the part of an order an admin actually comes here to move -->
    <div class="bg-card text-card-foreground flex flex-col gap-2.5 sm:gap-4 rounded-xl border border-border py-2.5 sm:py-4 shadow-sm">
      <div class="grid auto-rows-min gap-1.5 py-1 sm:py-2 px-3 sm:px-6">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path>
            <path d="m9 12 2 2 4-4"></path>
          </svg>
          {{ t.order?.status_section || 'Status & Payment' }}
        </div>
      </div>
      <div class="px-3 sm:px-6 space-y-2.5 sm:space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
          <FormSelect
            v-model="form.payment_status"
            :label="t.order?.payment_status || 'Payment Status'"
            :options="paymentStatuses"
            :error="errorFor('payment_status')"
            required
          />
          <FormSelect
            v-model="form.delivery_status"
            :label="t.order?.delivery_status || 'Delivery Status'"
            :options="deliveryStatuses"
            :error="errorFor('delivery_status')"
            required
          />
          <FormSelect
            v-model="form.order_status"
            :label="t.order?.order_status || 'Order Status'"
            :options="orderStatusChoices"
            :error="errorFor('order_status')"
            required
          />
          <FormSelect
            v-model="form.payment_type"
            :label="t.order?.payment_type || 'Payment Type'"
            :options="paymentTypes"
            :error="errorFor('payment_type')"
            required
          />
        </div>

        <!-- Only asked for when it applies, and required then: "canceled,
             reason unknown" is the answer nobody can act on later. -->
        <FormInput
          v-if="form.payment_status === 'canceled'"
          v-model="form.cancel_reason"
          :label="t.order?.cancel_reason || 'Cancel Reason'"
          :error="errorFor('cancel_reason')"
          required
          :counter-max="255"
        />

        <p v-if="paymentTypeChanged" class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-2 text-xs text-amber-500">
          {{ t.order?.payment_type_changed_hint || 'Changing the payment type does not move the money — confirm the wallet or the courier before saving.' }}
        </p>
      </div>
    </div>

    <!-- Whether this customer bought on a card, and which one. Above the
         customer block because it is the thing that explains the amounts. -->
    <OrderMembershipCard :membership="membership" :current-number="form.membership_number" />

    <!-- Customer -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="grid auto-rows-min gap-1.5 py-2 px-6">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>
          </svg>
          {{ t.order?.customer || 'Customer' }}
        </div>
      </div>
      <div class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <FormInput
            v-model="form.customer_full_name"
            :label="t.order?.customer_name || 'Full Name'"
            :error="errorFor('customer_full_name')"
            required
          />
          <FormInput
            v-model="form.customer_phone"
            :label="t.order?.customer_phone || 'Phone'"
            :error="errorFor('customer_phone')"
            required
            dir="ltr"
          />
          <FormInput
            v-model="form.membership_number"
            :label="t.order?.membership_number || 'Membership No.'"
            :error="errorFor('membership_number')"
          />
        </div>
        <FormTextarea
          v-model="form.customer_address"
          :label="t.order?.customer_address || 'Address'"
          :error="errorFor('customer_address')"
          rows="2"
        />

        <!-- Delivery address in detail — the same fields a member address
             keeps, so the courier gets a door rather than a paragraph. -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <FormSelect
            v-model="form.customer_address_type"
            :label="t.order?.address_type || 'Address Type'"
            :options="[{ value: '', label: t.order?.address_type_none || '— None —' }, ...addressTypeOptions]"
            :error="errorFor('customer_address_type')"
          />
          <FormInput
            v-model="form.customer_street"
            :label="t.order?.customer_street || 'Street'"
            :error="errorFor('customer_street')"
          />
          <FormInput
            v-model="form.customer_governorate"
            :label="t.order?.customer_governorate || 'Governorate'"
            :error="errorFor('customer_governorate')"
          />
          <FormInput
            v-model="form.customer_city"
            :label="t.order?.customer_city || 'City'"
            :error="errorFor('customer_city')"
          />
          <FormInput
            v-model="form.customer_building_number"
            :label="t.order?.customer_building_number || 'Building No.'"
            :error="errorFor('customer_building_number')"
          />
          <FormInput
            v-model="form.customer_apartment_number"
            :label="t.order?.customer_apartment_number || 'Apartment No.'"
            :error="errorFor('customer_apartment_number')"
          />
          <FormInput
            v-model="form.customer_floor_number"
            :label="t.order?.customer_floor_number || 'Floor No.'"
            :error="errorFor('customer_floor_number')"
          />
          <FormInput
            v-model="form.customer_special_mark"
            :label="t.order?.customer_special_mark || 'Special Mark'"
            :error="errorFor('customer_special_mark')"
            :placeholder="t.order?.customer_special_mark_placeholder || 'e.g. green gate, next to pharmacy'"
          />
        </div>
        <FormTextarea
          v-model="form.notes"
          :label="t.order?.notes || 'Notes'"
          :error="errorFor('notes')"
          rows="2"
        />
      </div>
    </div>

    <!-- Lines -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="grid auto-rows-min gap-1.5 py-2 px-6">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>
          </svg>
          {{ t.order?.products || 'Products' }}
        </div>
        <!-- Editing a line rewrites the archive of what was sold, so say so
             once rather than letting an admin find out from a report. -->
        <p class="text-xs text-muted-foreground">
          {{ t.order?.lines_hint || 'These lines are the record of what was sold. Editing one changes the order history — every change is logged.' }}
        </p>
      </div>

      <div class="px-6 space-y-3">
        <div v-if="form.products.length" class="space-y-3">
          <div
            v-for="(line, index) in form.products"
            :key="line.id ?? `new-${index}`"
            class="rounded-lg border border-border p-3 space-y-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-2 min-w-0">
                <img
                  v-if="line.image"
                  :src="line.image"
                  :alt="translatedName(line.name, locale)"
                  class="w-10 h-10 rounded-md border border-border object-cover shrink-0"
                />
                <div class="min-w-0">
                  <p class="text-sm font-medium break-words">{{ translatedName(line.name, locale) || (t.order?.unnamed_line || 'Unnamed line') }}</p>
                  <p class="text-[11px] text-muted-foreground">
                    {{ line.id ? `#${line.id}` : (t.order?.new_line || 'New line') }}
                    <span v-if="line.slug"> · {{ line.slug }}</span>
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="removeLine(index)"
                class="inline-flex items-center gap-1 rounded-md border border-destructive/30 bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/20 transition-colors cursor-pointer shrink-0"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                {{ t.common?.remove || 'Remove' }}
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <FormInput
                v-model="line.name.ar"
                :label="`${t.order?.product || 'Product'} (AR)`"
                :error="errorFor(`products.${index}.name`)"
                dir="rtl"
              />
              <FormInput
                v-model="line.name.en"
                :label="`${t.order?.product || 'Product'} (EN)`"
                dir="ltr"
              />
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
              <FormInput
                v-model="line.quantity"
                :label="t.order?.quantity || 'Qty'"
                type="number"
                min="1"
                step="1"
                :error="errorFor(`products.${index}.quantity`)"
                required
              />
              <FormInput
                v-model="line.old_price"
                :label="t.order?.old_price || 'Old Price'"
                type="number"
                min="0"
                step="0.01"
                :error="errorFor(`products.${index}.old_price`)"
              />
              <FormInput
                v-model="line.new_price"
                :label="t.order?.unit_price || 'Unit Price'"
                type="number"
                min="0"
                step="0.01"
                :error="errorFor(`products.${index}.new_price`)"
              />
              <FormInput
                v-model="line.cost_price"
                :label="t.order?.cost_price || 'Cost'"
                type="number"
                min="0"
                step="0.01"
                :error="errorFor(`products.${index}.cost_price`)"
              />
              <FormInput
                v-model="line.profit_price"
                :label="t.order?.profit_price || 'Profit'"
                type="number"
                min="0"
                step="0.01"
                :error="errorFor(`products.${index}.profit_price`)"
              />
            </div>

            <!-- Derived here and recomputed server-side; never posted, so it
                 cannot drift away from its own quantity and price. -->
            <p class="text-xs text-muted-foreground">
              {{ t.order?.line_total || 'Line Total' }}:
              <span class="font-semibold text-foreground tabular-nums">{{ formatPrice(lineTotal(line)) }}</span>
            </p>
          </div>
        </div>

        <p v-else class="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
          {{ t.order?.no_products || 'This order has no product lines.' }}
        </p>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end pt-1">
          <div class="flex-1">
            <FormSearchableSelect
              v-model="productToAdd"
              :label="t.order?.add_product || 'Add a product'"
              :options="productOptions"
              :placeholder="t.order?.add_product_placeholder || 'Search the catalogue...'"
            />
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              @click="addSelectedProduct"
              :disabled="!productToAdd"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 cursor-pointer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path><path d="M12 5v14"></path>
              </svg>
              {{ t.common?.add || 'Add' }}
            </button>
            <button
              type="button"
              @click="orderStore.addBlankLine()"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 cursor-pointer"
            >
              {{ t.order?.add_custom_line || 'Custom line' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Amounts -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="grid auto-rows-min gap-1.5 py-2 px-6">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <line x1="12" x2="12" y1="2" y2="22"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
          {{ t.order?.amounts || 'Amounts' }}
        </div>
      </div>
      <div class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <FormInput
            v-model="form.total_amount_before_discount"
            :label="t.order?.total_amount_before_discount || 'Before Discount'"
            type="number"
            min="0"
            step="0.01"
            :error="errorFor('total_amount_before_discount')"
          />
          <FormInput
            v-model="form.total_amount"
            :label="t.order?.total_amount || 'Total Amount'"
            type="number"
            min="0"
            step="0.01"
            :error="errorFor('total_amount')"
            required
          />
          <div class="space-y-1.5">
            <FormInput
              v-model="form.total_paid"
              :label="t.order?.total_paid || 'Total Paid'"
              type="number"
              min="0"
              step="0.01"
              :error="errorFor('total_paid')"
              required
            />
            <!-- The common case, one click: the customer paid what they were
                 charged. It copies the amount rather than adding to what is
                 already there, so clicking twice cannot double the payment. -->
            <button
              type="button"
              @click="orderStore.applyFullPayment()"
              :disabled="alreadyPaidInFull"
              class="inline-flex w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-emerald-500/40 px-2.5 py-1.5 text-xs font-medium text-emerald-500 transition-colors hover:bg-emerald-500/15 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent cursor-pointer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"></path>
              </svg>
              {{ t.order?.paid_full_price || 'Paid the full price' }}
            </button>
            <p class="text-xs" :class="settlement.tone">{{ settlement.text }}</p>
          </div>
        </div>

        <!-- Discount or full price, as the amounts stand right now: the gap
             between what the order would have cost and what it is charging.
             Derived from the form rather than the saved order so an admin
             editing either figure sees what they are about to save. -->
        <div :class="['flex flex-col gap-1.5 rounded-md border px-3 py-2 sm:flex-row sm:items-center sm:justify-between', discount.box]">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="discount.tone">
              <line x1="19" x2="5" y1="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle>
            </svg>
            <span class="text-sm font-semibold" :class="discount.tone">{{ discount.label }}</span>
          </div>
          <p class="text-xs" :class="discount.tone">{{ discount.detail }}</p>
        </div>

        <!--
          Delivery: what the courier charged us and what the buyer paid. The
          price is already part of the amount charged above — correcting it here
          does not re-total the order, because what was actually taken from the
          buyer is a fact, not a formula. The profit is read-only: the server
          stores `price - cost` however this form is filled in.
        -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <FormInput
            v-model="form.delivery_cost"
            :label="t.order?.delivery_cost || 'Delivery cost'"
            type="number"
            min="0"
            step="0.01"
            :error="errorFor('delivery_cost')"
          />
          <FormInput
            v-model="form.delivery_price"
            :label="t.order?.delivery_price || 'Delivery price'"
            type="number"
            min="0"
            step="0.01"
            :error="errorFor('delivery_price')"
          />
          <div class="space-y-1.5">
            <label class="text-sm font-medium">{{ t.order?.delivery_profit || 'Delivery profit' }}</label>
            <output
              class="flex h-9 w-full items-center rounded-md border border-border bg-muted/40 px-3 text-sm font-semibold tabular-nums"
              :class="orderStore.deliveryProfit < 0 ? 'text-red-500' : 'text-emerald-500'"
            >
              {{ formatPrice(orderStore.deliveryProfit) }}
            </output>
          </div>
        </div>

        <!-- The total is stored, not derived: a membership discount lives in
             the gap between the lines and what was charged. So the lines total
             is offered, never applied silently. -->
        <div
          v-if="totalDiffersFromLines"
          class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-2"
        >
          <p class="text-xs text-amber-500 flex-1">
            {{ t.order?.lines_total || 'Lines total' }}: <span class="font-semibold tabular-nums">{{ formatPrice(orderStore.linesTotal) }}</span>
            — {{ t.order?.total_mismatch || 'this differs from the amount charged.' }}
          </p>
          <button
            type="button"
            @click="orderStore.applyLinesTotal()"
            class="inline-flex items-center justify-center rounded-md border border-amber-500/40 px-2 py-1 text-xs font-medium text-amber-500 hover:bg-amber-500/20 transition-colors cursor-pointer shrink-0"
          >
            {{ t.order?.use_lines_total || 'Use lines total' }}
          </button>
        </div>

        <FormInput
          v-model="form.source"
          :label="t.order?.source || 'Source'"
          :error="errorFor('source')"
          :counter-max="32"
        />
      </div>
    </div>

    <!-- Receipts: shown so the admin can actually see what the buyer sent, and
         add to — a transfer confirmed over the phone leaves evidence somebody
         has to file. Add-only, uncapped: nothing here deletes a receipt, on
         purpose. An admin who does not believe one moves the payment status,
         which order_logs attributes and dates; a deleted file records nothing. -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="grid auto-rows-min gap-1.5 py-2 px-6">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path>
            <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path>
          </svg>
          {{ t.order?.receipts || 'Transfer Receipts' }}
          <span v-if="visibleReceiptCount" class="text-xs font-normal text-muted-foreground">
            {{ visibleReceiptCount }}
          </span>
        </div>
        <p class="text-xs text-muted-foreground">
          {{ t.order?.receipt_hint || 'A receipt is a claim — confirm it against the wallet.' }}
        </p>
      </div>

      <div class="px-6 space-y-4">
        <p v-if="errorFor('receipts')" class="rounded-md border border-destructive/30 bg-destructive/10 px-2.5 py-2 text-xs text-destructive break-words">
          {{ errorFor('receipts') }}
        </p>

        <div v-if="visibleReceipts.length || orderStore.newReceipts.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
          <!-- Already on the order -->
          <figure
            v-for="receipt in visibleReceipts"
            :key="receipt.id"
            class="space-y-1"
          >
            <div class="relative group">
              <img
                v-if="!isPdf(receipt)"
                :src="receipt.url"
                :alt="receipt.name"
                class="w-full h-28 rounded-lg border border-border object-cover transition hover:opacity-90 cursor-zoom-in"
                @click="openReceipt(receipt)"
              />
              <button
                v-else
                type="button"
                class="w-full h-28 rounded-lg border border-border bg-muted/40 flex flex-col items-center justify-center gap-1 text-muted-foreground hover:bg-muted/60 transition-colors cursor-zoom-in"
                @click="openPdf(receipt.url)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                  <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                </svg>
                <span class="text-[10px]">PDF</span>
              </button>

            </div>
            <figcaption class="flex items-center gap-1">
              <span class="text-[11px] text-muted-foreground truncate flex-1" :title="receipt.name">{{ receipt.name }}</span>
              <span v-if="receipt.uploaded_at" class="text-[10px] text-muted-foreground shrink-0">{{ receiptDate(receipt.uploaded_at) }}</span>
            </figcaption>
          </figure>

          <!-- Picked this session, saved with the next update -->
          <figure v-for="(file, index) in orderStore.newReceipts" :key="`${file.name}-${file.lastModified}-${index}`" class="space-y-1">
            <div class="w-full h-28 rounded-lg border border-dashed border-primary/50 bg-primary/5 flex flex-col items-center justify-center gap-1 text-primary overflow-hidden px-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline><line x1="12" x2="12" y1="3" y2="15"></line>
              </svg>
              <span class="text-[10px] font-medium truncate max-w-full" :title="file.name">{{ file.name }}</span>
              <span class="text-[10px] text-muted-foreground">{{ formatBytes(file.size) }}</span>
            </div>
            <figcaption class="flex items-center justify-end">
              <button
                type="button"
                class="inline-flex items-center gap-0.5 rounded-md border border-destructive/30 bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive hover:bg-destructive/20 transition-colors cursor-pointer shrink-0"
                @click="orderStore.unqueueReceipt(index)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
            </figcaption>
          </figure>
        </div>

        <div v-else class="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
          {{ t.order?.no_receipts || 'No receipts have been sent against this order.' }}
        </div>

        <!-- The picker sits below the grid so adding reads as one action, and
             is never hidden: an order takes as much evidence as it takes. -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
          <label
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
            </svg>
            {{ t.order?.receipt_add || 'Add receipts' }}
            <input
              type="file"
              class="hidden"
              multiple
              :accept="ACCEPTED_MIME_TYPES.join(',')"
              @change="onReceiptsPicked"
            />
          </label>
          <p class="text-xs text-muted-foreground">
            {{ t.order?.receipt_accept_hint || 'Photos (JPEG, PNG, WebP, HEIC) or PDF, up to 5 MB each.' }}
            {{ t.order?.receipt_append_only || 'Receipts are added, never removed — an order keeps every piece of evidence it was sent.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Provenance, shown but not editable -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
      <div class="p-3 border-b border-border flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
          <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <h2 class="text-sm font-semibold">{{ t.order?.origin || 'Origin' }}</h2>
        <span class="text-xs text-muted-foreground">{{ t.order?.origin_readonly || 'Recorded when the order arrived — not editable.' }}</span>
      </div>
      <div class="p-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.order_code || 'Order Code' }}</label>
          <p class="text-sm font-mono mt-0.5">{{ order.order_code }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.visitor_ip || 'Visitor IP' }}</label>
          <p class="text-sm font-mono mt-0.5" dir="ltr">{{ order.ip_address || '—' }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.created_at || 'Created At' }}</label>
          <p class="text-sm mt-0.5 tabular-nums">{{ order.created_at || '—' }}</p>
        </div>
        <div class="sm:col-span-3">
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.user_agent || 'User Agent' }}</label>
          <p class="text-xs mt-0.5 text-muted-foreground break-all" dir="ltr">{{ order.user_agent || '—' }}</p>
        </div>
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import ImageLightbox from "@/Components/ui/ImageLightbox.vue";
import { useNotification } from "@/composables/useNotification";
import { FormInput, FormSearchableSelect, FormSelect, FormTextarea } from "@/Components/form";
import { useOrderStore } from "../../Stores/OrderStore";
import { formatPrice, translatedName } from "../../orderDisplay.js";
import OrderMembershipCard from "./OrderMembershipCard.vue";

const props = defineProps({
  order: { type: Object, required: true },
  membership: {
    type: Object,
    default: () => ({ status: 'none', number: null, earns_member_price: false, card: null }),
  },
  paymentStatuses: { type: Array, default: () => [] },
  deliveryStatuses: { type: Array, default: () => [] },
  orderStatuses: { type: Array, default: () => [] },
  paymentTypes: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const orderStore = useOrderStore();
const { form } = storeToRefs(orderStore);

/* Server-side rules come back keyed by path (`products.2.quantity`), and the
   form's own errors live on the Inertia form — either can be the reason a
   field is red. */
const errorFor = (field) => orderStore.validationErrors?.[field] || form.value.errors?.[field] || '';

const productToAdd = ref(null);

/* Same shape as `addressTypeOptions`: the server sends the enum's English
   labels, and the admin's own wording wins where there is one. */
const orderStatusChoices = computed(() =>
  props.orderStatuses.map(option => ({
    value: option.value,
    label: t.value.order?.[`order_status_${option.value}`] || option.label,
  }))
);

const addressTypeOptions = computed(() =>
  (page.props.addressTypeOptions || []).map(option => ({
    value: option.value,
    label: t.value.order?.[`address_type_${option.value}`] || option.label,
  }))
);

const productOptions = computed(() =>
  props.products.map(product => ({
    value: product.id,
    label: translatedName(product.name, locale.value) || product.slug || `#${product.id}`,
  }))
);

const addSelectedProduct = () => {
  const product = props.products.find(candidate => candidate.id === productToAdd.value);
  if (!product) return;
  orderStore.addLineFromProduct(product);
  productToAdd.value = null;
};

const removeLine = (index) => {
  const line = form.value.products[index];
  const name = translatedName(line?.name, locale.value) || (t.value.order?.unnamed_line || 'this line');
  // A line is the record of something sold; dropping one is not an undo away.
  if (confirm((t.value.order?.confirm_remove_line || 'Remove :name from this order?').replace(':name', name))) {
    orderStore.removeLine(index);
  }
};

const lineTotal = (line) => {
  const quantity = Number(line.quantity) || 0;
  const price = Number(line.new_price) || 0;
  return Math.round(price * quantity * 100) / 100;
};

const totalDiffersFromLines = computed(() => {
  const charged = Number(form.value.total_amount);
  if (!Number.isFinite(charged)) return false;
  /* Delivery is charged on top of the lines, so the figure to compare against
     is lines + delivery. Without it every storefront order that pays for
     delivery would report a mismatch it does not have. */
  return Math.abs(charged - (orderStore.linesTotal + (Number(form.value.delivery_price) || 0))) > 0.009;
});

const paymentTypeChanged = computed(() => form.value.payment_type !== props.order.payment_type);

/* Settled, short, or over — all three are worth naming. `outstanding` is
   signed, so an overpayment reads as one rather than as a settled order. */
const alreadyPaidInFull = computed(() => Math.abs(orderStore.outstanding) < 0.005);

const settlement = computed(() => {
  const owed = orderStore.outstanding;
  if (Math.abs(owed) < 0.005) {
    return { text: t.value.order?.paid_in_full || 'Paid in full.', tone: 'text-emerald-500' };
  }
  if (owed > 0) {
    return {
      text: (t.value.order?.outstanding_amount || 'Outstanding: :amount').replace(':amount', formatPrice(owed)),
      tone: 'text-amber-500',
    };
  }
  return {
    text: (t.value.order?.overpaid || 'Overpaid by :amount').replace(':amount', formatPrice(Math.abs(owed))),
    tone: 'text-sky-500',
  };
});

/*
  Three answers, not two. A discount is a figure; "full price" is the absence
  of one; and an order with no before-discount figure at all was never priced
  against a member price, which is different from having been offered nothing.
*/
const discount = computed(() => {
  const saved = orderStore.membershipDiscount;
  const before = Number(form.value.total_amount_before_discount);

  if (saved === null) {
    return {
      label: t.value.order?.price_unknown || 'No comparison price',
      detail: t.value.order?.price_unknown_hint || 'This order has no before-discount figure to compare against.',
      tone: 'text-muted-foreground',
      box: 'border-border bg-muted/30',
    };
  }

  if (saved > 0.005) {
    const percent = before > 0 ? Math.round((saved / before) * 100) : null;
    return {
      label: (t.value.order?.discounted || 'Discounted — saved :amount').replace(':amount', formatPrice(saved)),
      detail: (t.value.order?.discounted_hint || ':before before discount → :after charged:percent')
        .replace(':before', formatPrice(before))
        .replace(':after', formatPrice(Number(form.value.total_amount)))
        .replace(':percent', percent === null ? '' : ` (−${percent}%)`),
      tone: 'text-emerald-500',
      box: 'border-emerald-500/40 bg-emerald-500/10',
    };
  }

  if (saved < -0.005) {
    /* Charged MORE than the before-discount figure — not a discount at all,
       and almost always a typo in one of the two boxes. */
    return {
      label: t.value.order?.price_above_list || 'Charged above the list price',
      detail: (t.value.order?.price_above_list_hint || 'Charged :after against a list price of :before — check both figures.')
        .replace(':after', formatPrice(Number(form.value.total_amount)))
        .replace(':before', formatPrice(before)),
      tone: 'text-red-400',
      box: 'border-red-500/40 bg-red-500/10',
    };
  }

  return {
    label: t.value.order?.full_price || 'Full price — no discount',
    detail: t.value.order?.full_price_hint || 'The customer is paying the full price of the items.',
    tone: 'text-zinc-400',
    box: 'border-zinc-500/40 bg-zinc-500/10',
  };
});

/* Receipts: the same file types the buyer's upload endpoint accepts, mirrored
   client-side so the picker refuses a video before it is uploaded rather than
   after a round trip. There is no count to mirror — the collection is
   uncapped, and append-only in both places. */
const ACCEPTED_MIME_TYPES = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/heic',
  'application/pdf',
];

/* Everything on the order, oldest first — the order they arrived in is the
   order they were sent in, which is how an admin reads a part-paid transfer. */
const visibleReceipts = computed(() => orderStore.orderReceipts || []);

const visibleReceiptCount = computed(() =>
  orderStore.orderReceipts.length + orderStore.newReceipts.length
);

const isPdf = (receipt) => /\.pdf$/i.test(receipt?.name || '');

/* When it arrived, so a receipt sent a week after the order reads as the
   second payment it is rather than as a duplicate of the first. */
const receiptDate = (value) => {
  try {
    return new Date(value).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-GB', {
      day: 'numeric',
      month: 'short',
    });
  } catch {
    return '';
  }
};

const formatBytes = (bytes) => {
  if (!Number.isFinite(bytes)) return '';
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  return `${Math.round(bytes / 1024)} KB`;
};

const onReceiptsPicked = (event) => {
  try {
    orderStore.queueReceipts(event.target.files);
  } catch (error) {
    // AGENTS.md: front-end failures are reported, not swallowed.
    reportClientError('Receipt file pick failed', { message: error?.message }, props.order.order_code);
    useNotification().error(t.value.common?.unexpected_error || 'Something went wrong.');
  } finally {
    event.target.value = '';
  }
};

// Receipts get their own viewer: paging through them is exactly how an admin
// checks one against the wallet.
const lightboxImages = computed(() =>
  (orderStore.orderReceipts || [])
    .filter(receipt => !isPdf(receipt))
    .map(receipt => ({ url: receipt.url, alt: receipt.name })),
);

const lightboxIndex = ref(null);

const openReceipt = (receipt) => {
  const at = lightboxImages.value.findIndex(img => img.url === receipt.url);
  if (at !== -1) lightboxIndex.value = at;
};

const openPdf = (url) => {
  window.open(url, '_blank', 'noopener');
};

function reportClientError(message, extra, orderCode) {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        message,
        route: window.location.pathname,
        fatal: false,
        extra: { order_code: orderCode, ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}
</script>
