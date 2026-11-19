/**
=========================================================================
=========================================================================
Template Name: Datta Able - Tailwind Admin Template
Author: CodedThemes
Support: https://codedthemes.support-hub.io/
File: themes.js  (FIXED VERSION)
=========================================================================
Fixes applied:
 1. First-load bug: localStorage.getItem('theme') returning null was being
    passed straight into layout_change(), which set data-pc-theme="null"
    (a literal string) instead of "light"/"dark". This broke every CSS
    rule scoped to [data-pc-theme="dark"] / [data-pc-theme="light"]
    (tables, text colors, cards, etc.) until the user manually toggled
    the switch once.
 2. Sidebar logo was never updated on theme change (line was commented out).
 3. Only the dark/light "theme" key was persisted to localStorage — preset
    color, header/navbar/logo/caption color, sidebar theme, RTL/LTR,
    container mode, menu icon style, nav image, caption visibility and
    layout type were never saved, so a page refresh/navigation reset them
    to defaults ("consistency" issue).
 4. Inconsistent attribute naming: data-pc-sidebar_theme (underscore) vs
    every other attribute using hyphens (data-pc-theme, data-pc-layout,
    etc). Standardized to data-pc-sidebar-theme.
    >>> IMPORTANT: if your CSS/SCSS specifically targets
        [data-pc-sidebar_theme] with an underscore, update it to
        [data-pc-sidebar-theme] as well, otherwise sidebar theming will
        silently stop working. Search your scss files for "sidebar_theme".
 5. All settings are now restored together on DOMContentLoaded so table
    theme, text color, logo, sidebar, header/navbar colors etc. stay
    consistent across reloads and page navigations.
=========================================================================
*/

'use strict';

var rtl_flag = false;
var dark_flag = false;

// ---------------------------------------------------------------------
// Central place that knows every localStorage key we use.
// ---------------------------------------------------------------------
var THEME_KEYS = {
  theme: 'theme', // 'light' | 'dark'
  preset: 'preset', // preset-1 ... preset-10 class name
  layout: 'pc_layout', // vertical/horizontal etc (data-pc-layout)
  direction: 'pc_direction', // 'true' (rtl) / 'false' (ltr)
  header: 'pc_header',
  navbar: 'pc_navbar',
  logo: 'pc_logo',
  caption: 'pc_caption',
  sidebarTheme: 'pc_sidebar_theme', // 'true' / 'false'
  container: 'pc_container', // 'true' / 'false'
  drpMenuIcon: 'pc_drp_menu_icon',
  drpMenuLinkIcon: 'pc_drp_menu_link_icon',
  navImg: 'pc_navimg',
  sidebarCaption: 'pc_sidebar_caption'
};

document.addEventListener('DOMContentLoaded', function () {
  if (typeof Storage !== 'undefined') {
    restore_all_settings();
  }
});

// ---------------------------------------------------------------------
// Restore every persisted setting on load, in one place, so nothing
// gets left in its default state after theme/color was changed earlier.
// ---------------------------------------------------------------------
function restore_all_settings() {
  // --- Dark / Light theme (fixed: no more null being passed in) ---
  var savedTheme = localStorage.getItem(THEME_KEYS.theme);
  if (savedTheme !== 'dark' && savedTheme !== 'light') {
    // No valid saved value yet -> fall back to system preference
    savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  layout_change(savedTheme);

  // Keep following the OS setting live ONLY if user never explicitly chose
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
    if (!localStorage.getItem(THEME_KEYS.theme)) {
      layout_change(event.matches ? 'dark' : 'light');
    }
  });

  // --- Preset color ---
  var savedPreset = localStorage.getItem(THEME_KEYS.preset);
  if (savedPreset) preset_change(savedPreset, false);

  // --- Layout type (vertical/horizontal/etc) ---
  var savedLayout = localStorage.getItem(THEME_KEYS.layout);
  if (savedLayout) main_layout_change(savedLayout, false);

  // --- RTL / LTR ---
  var savedDirection = localStorage.getItem(THEME_KEYS.direction);
  if (savedDirection) layout_rtl_change(savedDirection, false);

  // --- Header color ---
  var savedHeader = localStorage.getItem(THEME_KEYS.header);
  if (savedHeader) header_change(savedHeader, false);

  // --- Navbar color ---
  var savedNavbar = localStorage.getItem(THEME_KEYS.navbar);
  if (savedNavbar) navbar_change(savedNavbar, false);

  // --- Logo color ---
  var savedLogo = localStorage.getItem(THEME_KEYS.logo);
  if (savedLogo) logo_change(savedLogo, false);

  // --- Caption color ---
  var savedCaption = localStorage.getItem(THEME_KEYS.caption);
  if (savedCaption) caption_change(savedCaption, false);

  // --- Sidebar theme ---
  var savedSidebarTheme = localStorage.getItem(THEME_KEYS.sidebarTheme);
  if (savedSidebarTheme) layout_theme_sidebar_change(savedSidebarTheme, false);

  // --- Container mode ---
  var savedContainer = localStorage.getItem(THEME_KEYS.container);
  if (savedContainer) change_box_container(savedContainer, false);

  // --- Dropdown menu icon style ---
  var savedDrpMenuIcon = localStorage.getItem(THEME_KEYS.drpMenuIcon);
  if (savedDrpMenuIcon) drp_menu_icon_change(savedDrpMenuIcon, false);

  // --- Dropdown menu link icon style ---
  var savedDrpMenuLinkIcon = localStorage.getItem(THEME_KEYS.drpMenuLinkIcon);
  if (savedDrpMenuLinkIcon) drp_menu_link_icon_change(savedDrpMenuLinkIcon, false);

  // --- Nav image ---
  var savedNavImg = localStorage.getItem(THEME_KEYS.navImg);
  if (savedNavImg) nav_image_change(savedNavImg, false);

  // --- Sidebar caption show/hide ---
  var savedSidebarCaption = localStorage.getItem(THEME_KEYS.sidebarCaption);
  if (savedSidebarCaption) layout_caption_change(savedSidebarCaption, false);
}

// This event listener wires up all the click handlers (unchanged logic,
// just calls into the same functions which now also persist to storage).
document.addEventListener('DOMContentLoaded', function () {
  var if_exist = document.querySelectorAll('.preset-color');
  if (if_exist) {
    var preset_color = document.querySelectorAll('.preset-color > a');
    for (var h = 0; h < preset_color.length; h++) {
      var c = preset_color[h];
      c.addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        var presetValue = targetElement.getAttribute('data-value');
        preset_change(presetValue);
      });
    }

    var layout_btn = document.querySelectorAll('.theme-layout .btn');
    for (var t = 0; t < layout_btn.length; t++) {
      if (layout_btn[t]) {
        layout_btn[t].addEventListener('click', function (event) {
          event.stopPropagation();
          var targetElement = event.target;

          if (targetElement.tagName == 'SPAN') {
            targetElement = targetElement.parentNode;
          }
          if (targetElement.getAttribute('data-value') == 'true') {
            layout_change('light');
          } else {
            layout_change('dark');
          }
        });
      }
    }
  }

  // Initialize SimpleBar on elements with class 'pct-body' for custom scrollbar
  if (document.querySelector('.pct-body')) {
    new SimpleBar(document.querySelector('.pct-body'));
  }

  // Reset layout on button click
  var layout_reset = document.querySelector('#layoutreset');
  if (layout_reset) {
    layout_reset.addEventListener('click', function (e) {
      localStorage.clear();
      localStorage.setItem(THEME_KEYS.layout, 'vertical');
      location.reload();
    });
  }

  // ========================================

  var header_exist = document.querySelectorAll('.header-color');
  if (header_exist) {
    var header_color = document.querySelectorAll('.header-color > a');
    for (var h = 0; h < header_color.length; h++) {
      header_color[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        header_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var navbar_exist = document.querySelectorAll('.navbar-color');
  if (navbar_exist) {
    var navbar_color = document.querySelectorAll('.navbar-color > a');
    for (var h = 0; h < navbar_color.length; h++) {
      navbar_color[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        navbar_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var logo_exist = document.querySelectorAll('.logo-color');
  if (logo_exist) {
    var logo_color = document.querySelectorAll('.logo-color > a');
    for (var h = 0; h < logo_color.length; h++) {
      logo_color[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        logo_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var caption_exist = document.querySelectorAll('.caption-color');
  if (caption_exist) {
    var caption_color = document.querySelectorAll('.caption-color > a');
    for (var h = 0; h < caption_color.length; h++) {
      caption_color[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        caption_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var navimg_exist = document.querySelectorAll('.navbar-img');
  if (navimg_exist) {
    var navbar_img = document.querySelectorAll('.navbar-img > a');
    for (var h = 0; h < navbar_img.length; h++) {
      navbar_img[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        nav_image_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var drpicon_exist = document.querySelectorAll('.drp-menu-icon');
  if (drpicon_exist) {
    var drp_icon = document.querySelectorAll('.drp-menu-icon > a');
    for (var h = 0; h < drp_icon.length; h++) {
      drp_icon[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        drp_menu_icon_change(targetElement.getAttribute('data-value'));
      });
    }
  }

  var drplinkicon_exist = document.querySelectorAll('.drp-menu-link-icon');
  if (drplinkicon_exist) {
    var drp_link_icon = document.querySelectorAll('.drp-menu-link-icon > a');
    for (var h = 0; h < drp_link_icon.length; h++) {
      drp_link_icon[h].addEventListener('click', function (event) {
        var targetElement = event.target;
        if (targetElement.tagName == 'SPAN' || targetElement.tagName == 'I') {
          targetElement = targetElement.parentNode;
        }
        drp_menu_link_icon_change(targetElement.getAttribute('data-value'));
      });
    }
  }
  // ========================================
});

// -----------------------------------------------------------------------
// Sidebar caption hide/show
// -----------------------------------------------------------------------
function layout_caption_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.sidebarCaption, value);

  if (value == 'true') {
    document.getElementsByTagName('html')[0].setAttribute('data-pc-sidebar-caption', 'true');
  } else {
    document.getElementsByTagName('html')[0].setAttribute('data-pc-sidebar-caption', 'false');
  }

  var control = document.querySelector('.theme-nav-caption .btn.active');
  if (control) {
    control.classList.remove('active');
  }
  var newActiveButton = document.querySelector(`.theme-nav-caption .btn[data-value='${value}']`);
  if (newActiveButton) {
    newActiveButton.classList.add('active');
  }
}

// -----------------------------------------------------------------------
// Preset color
// -----------------------------------------------------------------------
function preset_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.preset, value);

  document.getElementsByTagName('html')[0].setAttribute('class', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var activePreset = document.querySelector('.preset-color > a.active');
    if (activePreset) activePreset.classList.remove('active');
    var newPreset = document.querySelector(".preset-color > a[data-value='" + value + "']");
    if (newPreset) newPreset.classList.add('active');
  }
}

// -----------------------------------------------------------------------
// Main layout type (vertical / horizontal / etc)
// -----------------------------------------------------------------------
function main_layout_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.layout, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-layout', value);

  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var activeLink = document.querySelector('.theme-main-layout > a.active');
    if (activeLink) {
      activeLink.classList.remove('active');
    }
    var newActiveLink = document.querySelector(".theme-main-layout > a[data-value='" + value + "']");
    if (newActiveLink) {
      newActiveLink.classList.add('active');
    }
  }
}

// -----------------------------------------------------------------------
// RTL / LTR
// -----------------------------------------------------------------------
function layout_rtl_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.direction, value);

  var htmlElement = document.getElementsByTagName('html')[0];

  if (value === 'true') {
    rtl_flag = true;
    htmlElement.setAttribute('data-pc-direction', 'rtl');
    htmlElement.setAttribute('dir', 'rtl');
    htmlElement.setAttribute('lang', 'ar');

    var activeButton = document.querySelector('.theme-direction .btn.active');
    if (activeButton) activeButton.classList.remove('active');
    var rtlButton = document.querySelector(".theme-direction .btn[data-value='true']");
    if (rtlButton) rtlButton.classList.add('active');
  } else {
    rtl_flag = false;
    htmlElement.setAttribute('data-pc-direction', 'ltr');
    htmlElement.setAttribute('dir', 'ltr');
    htmlElement.removeAttribute('lang');

    var activeButton2 = document.querySelector('.theme-direction .btn.active');
    if (activeButton2) activeButton2.classList.remove('active');
    var ltrButton = document.querySelector(".theme-direction .btn[data-value='false']");
    if (ltrButton) ltrButton.classList.add('active');
  }
}

// -----------------------------------------------------------------------
// Dark / Light theme  (this is the one that drives table + text colors)
// -----------------------------------------------------------------------
function layout_change(layout, persist) {
  // Guard: never let an invalid value slip through and break CSS matching
  if (layout !== 'dark' && layout !== 'light') {
    layout = 'light';
  }

  if (persist !== false) localStorage.setItem(THEME_KEYS.theme, layout);

  // Set the theme attribute on the <html> tag -> this is what your
  // SCSS/CSS [data-pc-theme="dark"] rules key off of (tables, text, etc.)
  document.getElementsByTagName('html')[0].setAttribute('data-pc-theme', layout);

  var btn_control = document.querySelector('.theme-layout .btn[data-value="default"]');
  if (btn_control) {
    btn_control.classList.remove('active');
  }

  var isDark = layout === 'dark';
  dark_flag = isDark;

  var logoSrc = isDark ? '../assets/images/logo-white.svg' : '../assets/images/logo-dark.svg';

  function updateLogo(selector) {
    var element = document.querySelector(selector);
    if (element) {
      element.setAttribute('src', logoSrc);
    }
  }

  // FIX: sidebar logo now updates along with the rest
  updateLogo('.pc-sidebar .m-header .logo-lg');
  updateLogo('.navbar-brand .logo-lg');
  updateLogo('.auth-main.v1 .auth-sidefooter img');
  updateLogo('.auth-logo');
  updateLogo('.footer-top .footer-logo');

  var activeControl = document.querySelector('.theme-layout .btn.active');
  if (activeControl) {
    activeControl.classList.remove('active');
  }

  var newActiveControl = document.querySelector(`.theme-layout .btn[data-value='${isDark ? 'false' : 'true'}']`);
  if (newActiveControl) {
    newActiveControl.classList.add('active');
  }
}

// -----------------------------------------------------------------------
// Box / container layout
// -----------------------------------------------------------------------
function change_box_container(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.container, value);

  var contentElement = document.querySelector('.pc-content');
  var footerElement = document.querySelector('.footer-wrapper');

  if (contentElement && footerElement) {
    if (value === 'true') {
      contentElement.classList.add('container');
      footerElement.classList.add('container');
      footerElement.classList.remove('container-fluid');
    } else {
      contentElement.classList.remove('container');
      footerElement.classList.remove('container');
      footerElement.classList.add('container-fluid');
    }

    var activeButton = document.querySelector('.theme-container .btn.active');
    if (activeButton) {
      activeButton.classList.remove('active');
    }

    var newActiveButton = document.querySelector(`.theme-container .btn[data-value='${value}']`);
    if (newActiveButton) {
      newActiveButton.classList.add('active');
    }
  }
}

// -----------------------------------------------------------------------
// Sidebar theme (light/dark sidebar independent of page theme)
// NOTE: attribute renamed data-pc-sidebar_theme -> data-pc-sidebar-theme
// Update your SCSS selector accordingly if it used the underscore version.
// -----------------------------------------------------------------------
function layout_theme_sidebar_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.sidebarTheme, value);

  var sidebarLogo = document.querySelector('.pc-sidebar .m-header .logo-lg');

  if (value == 'true') {
    document.getElementsByTagName('html')[0].setAttribute('data-pc-sidebar-theme', 'true');
    if (sidebarLogo) {
      sidebarLogo.setAttribute('src', '../assets/images/logo-dark.svg');
    }
  } else {
    document.getElementsByTagName('html')[0].setAttribute('data-pc-sidebar-theme', 'false');
    if (sidebarLogo) {
      sidebarLogo.setAttribute('src', '../assets/images/logo-white.svg');
    }
  }

  var control = document.querySelector('.theme-nav-layout .btn.active');
  if (control) {
    control.classList.remove('active');
    var newControl = document.querySelector(".theme-nav-layout .btn[data-value='" + value + "']");
    if (newControl) newControl.classList.add('active');
  }
}

function header_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.header, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-header', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.header-color > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".header-color > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function navbar_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.navbar, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-navbar', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.navbar-color > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".navbar-color > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function logo_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.logo, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-logo', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.logo-color > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".logo-color > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function caption_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.caption, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-caption', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.caption-color > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".caption-color > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function drp_menu_icon_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.drpMenuIcon, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-drp-menu-icon', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.drp-menu-icon > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".drp-menu-icon > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function drp_menu_link_icon_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.drpMenuLinkIcon, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-drp-menu-link-icon', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.drp-menu-link-icon > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".drp-menu-link-icon > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}

function nav_image_change(value, persist) {
  if (persist !== false) localStorage.setItem(THEME_KEYS.navImg, value);

  document.getElementsByTagName('html')[0].setAttribute('data-pc-navimg', value);
  var control = document.querySelector('.pct-offcanvas');
  if (control) {
    var active = document.querySelector('.navbar-img > a.active');
    if (active) active.classList.remove('active');
    var newActive = document.querySelector(".navbar-img > a[data-value='" + value + "']");
    if (newActive) newActive.classList.add('active');
  }
}