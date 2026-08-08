# Task: Server-side card generation & download endpoint

1. Install `intervention/image-laravel` for image manipulation
2. Create `App\Services\CardGenerationService` - composites card image server-side
3. Add API endpoint `GET /api/v1/memberships/{membership}/card?mode=full|minimal`  
4. Update `MembershipResource` to include guaranteed `card_download_url`
5. Test the endpoint works
