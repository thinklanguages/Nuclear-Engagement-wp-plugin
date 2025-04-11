// admin/ts/nuclen-admin-ui.ts

document.addEventListener("DOMContentLoaded", () => {
    // Grab tab links as HTMLAnchorElements
    const tabs = document.querySelectorAll<HTMLAnchorElement>(".nav-tab");
    // Grab tab content sections as HTMLElements
    const contents = document.querySelectorAll<HTMLElement>(".nuclen-tab-content");
  
    tabs.forEach((tab) => {
      tab.addEventListener("click", (e) => {
        e.preventDefault();
        const target = tab.getAttribute("href");
        if (!target) return; // If href is missing, bail
  
        // Remove active class from all tabs, then add to the one that was clicked
        tabs.forEach((t) => t.classList.remove("nav-tab-active"));
        tab.classList.add("nav-tab-active");
  
        // Hide all tab contents, then show only the targeted one
        contents.forEach((c) => {
          c.style.display = "none";
        });
        const targetContent = document.querySelector<HTMLElement>(target);
        if (targetContent) {
          targetContent.style.display = "block";
        }
      });
    });
  
    // Initialize WP color pickers if available
    if (typeof wp !== "undefined" && typeof wp.wpColorPicker === "function") {
      document.querySelectorAll<HTMLElement>(".wp-color-picker-field").forEach((element) => {
        (wp.wpColorPicker as any).call(element);
      });
    }
  
    // Show/hide the custom theme section
    const customThemeSection = document.getElementById("nuclen-custom-theme-section");
    const themeRadios = document.querySelectorAll<HTMLInputElement>("input[name='nuclen_theme']");
  
    function nuclenUpdateCustomThemeVisibility() {
      const checkedInput = document.querySelector<HTMLInputElement>("input[name='nuclen_theme']:checked");
      // If either the input or the section is missing, do nothing
      if (!checkedInput || !customThemeSection) {
        return;
      }
  
      const selectedTheme = checkedInput.value;
      if (selectedTheme === "custom") {
        customThemeSection.classList.remove("nuclen-hidden");
      } else {
        customThemeSection.classList.add("nuclen-hidden");
      }
    }
  
    // Listen for theme radio changes
    themeRadios.forEach((radio) => {
      radio.addEventListener("change", nuclenUpdateCustomThemeVisibility);
    });
  
    // Initial run on page load
    nuclenUpdateCustomThemeVisibility();
  });
  