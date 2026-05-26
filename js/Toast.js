class Toast {
   static css = `
   @keyframes slideInFromTop {
      from {
         transform: translateY(-100%);
      }
      to {
         transform: translateY(0);
      }
   }
   @keyframes slideOutToTop {
      from {
         transform: translateY(0);
      }
      to {
         transform: translateY(-100%);
      }
   }

   .toast-container {
      position: fixed;
      top: 2rem;
      right: 2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999999;
      animation: slideInFromTop 0.3s ease-in-out;
   }

   .toast-notification {
      background-color: rgba(0, 0, 0, 0.9);
      color: white;
      padding: 1rem;
      border-radius: 8px;
      box-shadow: 0px 3px 20px 8px rgba(0, 0, 0, 0.3);
   }

   .toast-notification.success {
      background-color: hsl(143, 85%, 96%);
      font-weight: 600;
      color: hsl(140, 100%, 27%);
   }
   .toast-notification.error {
      background-color: hsl(359, 100%, 97%);
      font-weight: 600;
      color: hsl(360, 100%, 45%);
   }
   .toast-notification.default {
      background-color: white;
      font-weight: 600;
      color: black;
   }
   `;

   constructor(text, type = "default", duration = 4000) {
      this.text = text;
      this.type = type;
      this.duration = duration;
   }

   show() {
      this.create();
      // this.toastContainer.style.display = "flex";
      setTimeout(() => this.hide(), this.duration);
   }

   create() {
      if (!this.toastContainer) {
         this.toastContainer = document.createElement("div");
         this.toastContainer.classList.add("toast-container");
         document.body.appendChild(this.toastContainer);

         const style = document.createElement("style");
         style.textContent = Toast.css;
         document.head.appendChild(style);
      }

      this.toast = document.createElement("div");
      this.toast.classList.add("toast-notification");
      this.toast.classList.add(this.type);
      this.toast.textContent = this.text;
      this.toastContainer.appendChild(this.toast);
   }

   hide() {
      this.toastContainer.style.animation = "slideOutToTop 0.3s ease-in-out";
      setTimeout(() => {
         // this.toastContainer.style.display = "none";
         // this.toastContainer.style.animation = "";
         this.toastContainer.remove();
      }, 300);
      // destroy toast
   }
}