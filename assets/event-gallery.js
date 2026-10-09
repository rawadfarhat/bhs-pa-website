(() => {
  document.querySelectorAll("[data-event-gallery]").forEach((gallery) => {
    const items = [...gallery.querySelectorAll("[data-gallery-index]")];
    const dialog = gallery.querySelector(".event-gallery-dialog");
    const image = dialog?.querySelector("figure img");
    const caption = dialog?.querySelector("figcaption");
    const dialogThumbs = [...(dialog?.querySelectorAll("[data-dialog-gallery-index]") || [])];
    if (!dialog || !image || !caption || items.length === 0) return;
    const addLoader = (container) => {
      const overlay = document.createElement("span");
      overlay.className = "event-photo-loading";
      overlay.setAttribute("role", "status");
      overlay.textContent = "Loading photo…";
      container.append(overlay);
      return (loading, failed = false) => {
        container.classList.toggle("is-loading", loading);
        container.classList.toggle("has-error", failed);
        container.setAttribute("aria-busy", String(loading));
        overlay.textContent = failed ? "Photo could not be loaded" : "Loading photo…";
      };
    };
    [...items, ...dialogThumbs].forEach((thumb) => {
      const thumbnail = thumb.querySelector("img");
      const setLoading = addLoader(thumb);
      const settle = () => setLoading(false, thumbnail.naturalWidth === 0);
      thumbnail.addEventListener("load", settle);
      thumbnail.addEventListener("error", settle);
      setLoading(true);
      if (thumbnail.complete) settle();
    });
    const setPhotoLoading = addLoader(image.parentElement);
    let request = 0;
    let current = 0;
    const show = (index) => {
      const activeRequest = ++request;
      current = (index + items.length) % items.length;
      const item = items[current];
      setPhotoLoading(true);
      image.removeAttribute("src");
      image.alt = item.dataset.alt || "Event photo";
      const pending = new Image();
      pending.onload = () => {
        if (activeRequest !== request) return;
        image.src = pending.src;
        setPhotoLoading(false);
      };
      pending.onerror = () => {
        if (activeRequest === request) setPhotoLoading(false, true);
      };
      pending.src = item.dataset.large || item.dataset.medium;
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
    dialog.addEventListener("close", () => { ++request; image.removeAttribute("src"); setPhotoLoading(false); });
  });
})();
