// utils/formHelpers.ts

/**
 * Today's date as YYYY-MM-DD in the browser's local timezone.
 * `new Date().toISOString()` returns the UTC date, which is off by a day
 * for users ahead of UTC - use this instead for "default to today" fields.
 */
export const todayLocalDateString = (): string => {
  const now = new Date();
  const tzOffsetMs = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - tzOffsetMs).toISOString().split('T')[0];
};

/**
 * Prepares form data for submission, handling file uploads properly
 * @param formData The form data object
 * @param mode 'create' or 'edit' mode
 * @param fileFields Array of field names that contain file uploads
 * @returns FormData object ready for submission
 */
export const prepareFormDataWithFiles = (
  formData: Record<string, any>,
  mode: 'create' | 'edit',
  fileFields: string[] = []
): FormData => {
  const formDataObj = new FormData();
  
  // Add all fields to FormData
  Object.keys(formData).forEach(key => {
    // Skip empty file fields in edit mode
    if (fileFields.includes(key) && mode === 'edit' && !formData[key]) {
      return;
    }
    
    // Handle array values
    if (Array.isArray(formData[key])) {
      formData[key].forEach((item: any, index: number) => {
        formDataObj.append(`${key}[${index}]`, item);
      });
    } else {
      formDataObj.append(key, formData[key]);
    }
  });
  
  return formDataObj;
};

/**
 * Submits form data with file uploads to the server
 * @param endpoint API endpoint
 * @param formData Form data to submit
 * @param mode 'create' or 'edit' mode
 * @param id ID of the record (for edit mode)
 * @param fileFields Array of field names that contain file uploads
 * @param callbacks Callbacks for success and error
 */
export const submitFormWithFiles = (
  endpoint: string,
  formData: Record<string, any>,
  mode: 'create' | 'edit',
  id: number | null = null,
  fileFields: string[] = [],
  callbacks: {
    onSuccess?: (response: any) => void;
    onError?: (errors: any) => void;
  } = {}
) => {
  const { router } = require('@inertiajs/react');
  const formDataObj = prepareFormDataWithFiles(formData, mode, fileFields);
  
  if (mode === 'create') {
    router.post(endpoint, formDataObj, {
      onSuccess: callbacks.onSuccess,
      onError: callbacks.onError
    });
  } else if (mode === 'edit' && id) {
    // Use POST with _method=PUT for file uploads
    router.post(`${endpoint}/${id}?_method=PUT`, formDataObj, {
      onSuccess: callbacks.onSuccess,
      onError: callbacks.onError
    });
  }
};