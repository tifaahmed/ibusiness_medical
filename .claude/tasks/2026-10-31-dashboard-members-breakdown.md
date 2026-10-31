# Dashboard: member counter shows main vs relative boxes

## Request (verbatim)
/admin/dashboard  i want here when i click on the member counter to see two two boxs one for the main members and one for the relative

## Plan
Total Members StatCard opens a modal with two boxes (main / relatives), counts from backend, each linking to members list `?member_type=primary|relative`.

## PROGRESS
- [x] DashboardController: primary + relative counts in statistic `breakdown`
- [x] StatCard: emit click / Dashboard modal with two boxes
- [x] lang en/ar keys
- [x] build + check
