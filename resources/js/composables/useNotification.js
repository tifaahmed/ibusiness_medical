// Toast instance will be set by app.js if vue-toastification is available
let toastInstance = null;

// Function to set toast instance from app.js
export function setToastInstance(instance) {
  toastInstance = instance;
}

export function useNotification() {
  // If toast is available, use it
  if (toastInstance) {
    return {
      success(message) {
        toastInstance.success(message, {
          position: 'top-right',
          timeout: 5000,
          closeOnClick: true,
          pauseOnFocusLoss: true,
          pauseOnHover: true,
          draggable: true,
          draggablePercent: 0.6,
          showCloseButtonOnHover: false,
          hideProgressBar: false,
          closeButton: 'button',
          icon: true,
          rtl: false
        });
      },
      error(message) {
        toastInstance.error(message, {
          position: 'top-right',
          timeout: 7000,
          closeOnClick: true,
          pauseOnFocusLoss: true,
          pauseOnHover: true,
          draggable: true,
          draggablePercent: 0.6,
          showCloseButtonOnHover: false,
          hideProgressBar: false,
          closeButton: 'button',
          icon: true,
          rtl: false
        });
      },
      info(message) {
        toastInstance.info(message, {
          position: 'top-right',
          timeout: 5000,
          closeOnClick: true,
          pauseOnFocusLoss: true,
          pauseOnHover: true,
          draggable: true,
          draggablePercent: 0.6,
          showCloseButtonOnHover: false,
          hideProgressBar: false,
          closeButton: 'button',
          icon: true,
          rtl: false
        });
      },
      warning(message) {
        toastInstance.warning(message, {
          position: 'top-right',
          timeout: 6000,
          closeOnClick: true,
          pauseOnFocusLoss: true,
          pauseOnHover: true,
          draggable: true,
          draggablePercent: 0.6,
          showCloseButtonOnHover: false,
          hideProgressBar: false,
          closeButton: 'button',
          icon: true,
          rtl: false
        });
      }
    };
  }
  
  // Fallback implementation - will work until vue-toastification is installed
  return {
    success(message) {
      console.log('✅ Success:', message);
    },
    error(message) {
      console.error('❌ Error:', message);
    },
    info(message) {
      console.info('ℹ️ Info:', message);
    },
    warning(message) {
      console.warn('⚠️ Warning:', message);
    }
  };
}


