// Keeps the browser tab's title in step with the section of a page the
// attendee is looking at. The server renders the page-level title
// ("Explore | WordCamp Rajasthan 2026 | CampBuddy"); pages with in-page tabs
// (Explore, My Day) put the active section in front of it
// ("Sponsors | Explore | WordCamp Rajasthan 2026 | CampBuddy").

let baseTitle = null;

export function setSectionTitle(section) {
  // Captured lazily, on first use, so it's always the server-rendered title.
  baseTitle ??= document.title;
  document.title = section ? `${section} | ${baseTitle}` : baseTitle;
}
