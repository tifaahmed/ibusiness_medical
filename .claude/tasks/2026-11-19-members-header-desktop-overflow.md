# Members header buttons overflow on desktop
## المطلوب
admin/user/membership the buttons is outside the box in the desktop size, fix it
## PROGRESS
- [x] buttons row: removed sm:flex-shrink-0, now sm:max-w-full sm:min-w-0 sm:justify-end (wraps inside card); title min-w-[10rem]. Same class in 3 files (Member list, Role list, +1)
- [ ] user to eyeball at desktop width (vite not run)
