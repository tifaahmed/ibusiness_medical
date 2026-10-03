import axios from 'axios';
import { useNotification } from '@/composables/useNotification';

/**
 * The uploader the description editors take (`image-uploader`).
 *
 * The file is stored at once so the editor can show it; `onUploaded(path)` lets
 * the form remember the path, and the server ties it to the record as a hidden
 * gallery image when the form is saved. Resolves to a host-less "/storage/…" URL
 * (what the description keeps) or null on failure.
 */
export function useEditorImageUpload(scope, onUploaded) {
  return async (file) => {
    const body = new FormData();
    body.append('image', file);
    body.append('scope', scope);

    try {
      const { data } = await axios.post(route('admin.editor-image'), body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      onUploaded?.(data.path);
      return data.url;
    } catch (error) {
      axios.post('/api/v1/client-errors', {
        message: error?.message || 'Description image upload failed',
        stack: error?.stack,
        fatal: false,
        route: window.location.pathname,
        extra: { feature: 'editor-image-upload', scope, status: error?.response?.status },
      }).catch(() => {});
      useNotification().error(
        error?.response?.data?.errors?.image?.[0] || error?.response?.data?.message || 'Failed to upload the image'
      );
      return null;
    }
  };
}
