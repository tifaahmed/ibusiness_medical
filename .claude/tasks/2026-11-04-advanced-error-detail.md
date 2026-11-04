# Advanced error details everywhere (copyable report for the admin)

## المطلوب (حرفياً)
/user/membership/member-01019589830-7q95rm/edit الارور حصل هنا ... ان التعديل كان واقف من دون اسباب
لو مش عارف تجيب المشكله من هنا ياربت تحط رساله فيها تفاصيل اكثر فى رساله الارور عشان اقدر ابعتها فى المستقبل
وعمم ده على المشروع كله فى جزئيه advanced error

## القرارات
- Member 2192 update: cause unknown locally (prod log only). Test MemberUpdateUncheckedTest reproduces the payload on ibusiness_phpunit_test.
- Plan: App\Support\ErrorDetail (reference id + class + message + file:line + route + user + time, logged at error level) -> returned as errors.error_detail; ErrorTrackButton/recordResponse include it in the copied report; applied to every `withErrors(['error' => ...])` catch site.

## PROGRESS
- [x] found existing ErrorTrace + flash.error_debug (only 4 controllers used it)
- [x] CapturedError (log listener) + AttachErrorDebug middleware (web group, admin/* only) flash error_debug for ALL catch sites; errorTrack.recordResponse falls back to flash.error_debug; MemberStore + FamilyMemberCard use recordResponse
- [~] test AdvancedErrorDebugTest + MemberUpdateUncheckedTest on ibusiness_phpunit_test; vite build
