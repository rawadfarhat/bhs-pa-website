(() => {
  document.querySelectorAll("[data-event-gallery]").forEach((gallery) => {
    const items = [...gallery.querySelectorAll("[data-gallery-index]")];
    const dialog = gallery.querySelector(".event-gallery-dialog");
    const image = dialog?.querySelector("figure img");
    const caption = dialog?.querySelector("figcaption");
    const dialogThumbs = [...(dialog?.querySelectorAll("[data-dialog-gallery-index]") || [])];
    if (!dialog || !image || !caption || items.length === 0) return;
    let current = 0;
    const show = (index) => {
      current = (index + items.length) % items.length;
      const item = items[current];
      image.src = item.dataset.large || item.dataset.medium;
      image.alt = item.dataset.alt || "Event photo";
      caption.textContent = `${current + 1} of ${items.length}`;
      dialogThumbs.forEach((thumb, thumbIndex) => {
        thumb.setAttribute("aria-current", thumbIndex === current ? "true" : "false");
        if (thumbIndex === current) thumb.scrollIntoView({ block: "nearest", inline: "center" });
      });
    };
    const open = (index) => {
      show(index);
      dialog.showModal();
    };
    items.forEach((item, index) => item.addEventListener("click", () => open(index)));
    dialogThumbs.forEach((item, index) => item.addEventListener("click", () => show(index)));
    dialog.querySelector("[data-gallery-close]")?.addEventListener("click", () => dialog.close());
    dialog.querySelector("[data-gallery-previous]")?.addEventListener("click", () => show(current - 1));
    dialog.querySelector("[data-gallery-next]")?.addEventListener("click", () => show(current + 1));
    dialog.addEventListener("click", (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener("keydown", (event) => {
      if (event.key === "ArrowLeft") { event.preventDefault(); show(current - 1); }
      if (event.key === "ArrowRight") { event.preventDefault(); show(current + 1); }
    });
    dialog.addEventListener("close", () => { image.removeAttribute("src"); });
  });
})();
