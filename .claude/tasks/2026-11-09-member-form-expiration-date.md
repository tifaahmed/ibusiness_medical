# Show the expiration date on /admin/user/membership/create

## Request (verbatim)
admin/user/membership/create  انا عايزك تحط تاريخ الانتهائ الى كان مخفى قبل كده اظهره وخليني اتحكم فيه ويكون واضح فيه ان كده مده الكارت قد ايه وتخلص

## Decisions
- Field is in MemberForm.vue (v-if="false"). Show it, optional (backend already nullable), editable.
- Add quick duration buttons + a live line "card lasts N months/days, ends on DATE".

## PROGRESS
- [x] MemberForm.vue (shared by create + edit): field shown, presets, duration summary
- [ ] build check / verify in browser
