(function () {
  const appEl = document.getElementById("app");
  const boardEl = document.getElementById("board");
  const messageEl = document.getElementById("message");
  const movesEl = document.getElementById("moves-count");
  const undoButton = document.getElementById("undo-button");
  const autoCompleteButton = document.getElementById("auto-complete-button");

  let state = JSON.parse(appEl.dataset.state);
  let selected = null;
  let pendingCardClick = null;
  const DOUBLE_CLICK_DELAY = 250;

  const SUIT_SYMBOLS = { S: "♠", H: "♥", D: "♦", C: "♣" };
  const RANK_LABELS = {
    1: "A",
    2: "2",
    3: "3",
    4: "4",
    5: "5",
    6: "6",
    7: "7",
    8: "8",
    9: "9",
    10: "10",
    11: "J",
    12: "Q",
    13: "K",
  };

  function isRed(card) {
    const suit = card.slice(-1);
    return suit === "H" || suit === "D";
  }

  function cardLabel(card) {
    const suit = card.slice(-1);
    const rank = card.slice(0, -1);
    return `${rank}${SUIT_SYMBOLS[suit]}`;
  }

  function rankToCard(rank, suit) {
    return `${RANK_LABELS[rank]}${suit}`;
  }

  function render() {
    boardEl.innerHTML = "";

    const topRow = document.createElement("div");
    topRow.className = "top-row";

    const freeRow = document.createElement("div");
    freeRow.className = "freecells";
    state.freecells.forEach((card, i) => {
      freeRow.appendChild(makeSlot("free", i, card ? [card] : []));
    });
    topRow.appendChild(freeRow);

    const foundRow = document.createElement("div");
    foundRow.className = "foundations";
    ["S", "H", "D", "C"].forEach((suit) => {
      const rank = state.foundations[suit];
      const card = rank > 0 ? rankToCard(rank, suit) : null;
      foundRow.appendChild(
        makeSlot("foundation", suit, card ? [card] : [], suit),
      );
    });
    topRow.appendChild(foundRow);

    boardEl.appendChild(topRow);

    const colsRow = document.createElement("div");
    colsRow.className = "columns";
    state.columns.forEach((col, i) => {
      colsRow.appendChild(makeSlot("column", i, col));
    });
    boardEl.appendChild(colsRow);

    movesEl.textContent = `Coups : ${state.moves}`;
    undoButton.disabled = state.history.length === 0;

    if (state.won) {
      messageEl.textContent = "🎉 Gagné ! Bravo !";
      messageEl.className = "message win";
    }
  }

  function makeSlot(type, index, cards, emptySuit) {
    const slot = document.createElement("div");
    slot.className = `slot slot-${type}`;
    slot.dataset.type = type;
    slot.dataset.index = index;
    slot.addEventListener("click", () => {
      flushPendingCardClick();
      onSlotClick(type, index);
    });

    if (cards.length === 0) {
      if (emptySuit) {
        const symbol = document.createElement("span");
        symbol.className = "empty-symbol";
        symbol.textContent = SUIT_SYMBOLS[emptySuit];
        slot.appendChild(symbol);
      }
      return slot;
    }

    cards.forEach((card, ci) => {
      const cardEl = document.createElement("div");
      cardEl.className = `card ${isRed(card) ? "red" : "black"}`;
      cardEl.textContent = cardLabel(card);

      if (type === "column") {
        cardEl.style.top = `${2 + ci * 28}px`;
      }

      const selectedStart =
        selected &&
        selected.type === "column" &&
        String(selected.index) === String(index)
          ? cards.length - selected.count
          : cards.length - 1;
      if (
        selected &&
        selected.type === type &&
        String(selected.index) === String(index) &&
        ci >= selectedStart
      ) {
        cardEl.classList.add("selected");
      }

      cardEl.addEventListener("click", (e) => {
        e.stopPropagation();
        pendingCardClick = { type, index, cardIndex: ci };
        window.setTimeout(() => {
          if (
            pendingCardClick?.type === type &&
            pendingCardClick.index === index &&
            pendingCardClick.cardIndex === ci
          ) {
            flushPendingCardClick();
          }
        }, DOUBLE_CLICK_DELAY);
      });

      cardEl.addEventListener("dblclick", (e) => {
        e.stopPropagation();
        pendingCardClick = null;
        if (type === "column" && ci !== cards.length - 1) {
          return;
        }

        selected = null;
        sendAction("/auto-move", {
          from: { type, index, count: 1 },
        });
      });

      slot.appendChild(cardEl);
    });

    if (type === "column") {
      slot.style.minHeight = `${126 + (cards.length - 1) * 28}px`;
    }

    return slot;
  }

  function flushPendingCardClick() {
    if (!pendingCardClick) {
      return;
    }

    const click = pendingCardClick;
    pendingCardClick = null;
    onSlotClick(click.type, click.index, click.cardIndex);
  }

  function onSlotClick(type, index, cardIndex = null) {
    messageEl.textContent = "";
    messageEl.className = "message";

    if (!selected) {
      const hasCard =
        type === "column"
          ? state.columns[index].length > 0
          : type === "free"
            ? state.freecells[index] !== null
            : false;

      if (hasCard) {
        selected = {
          type,
          index,
          count:
            type === "column" && cardIndex !== null
              ? state.columns[index].length - cardIndex
              : 1,
        };
        render();
      }
      return;
    }

    if (selected.type === type && String(selected.index) === String(index)) {
      selected = null;
      render();
      return;
    }

    const from = selected;
    const to = { type, index };
    selected = null;

    sendAction("/move", { from, to });
  }

  function sendAction(url, payload = {}) {
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((r) => r.json())
      .then((data) => {
        state = data.state;
        if (!data.success) {
          messageEl.textContent = data.error;
          messageEl.className = "message error";
        }
        render();
      })
      .catch(() => {
        messageEl.textContent = "Erreur réseau, réessaie.";
        messageEl.className = "message error";
      });
  }

  undoButton.addEventListener("click", () => sendAction("/undo"));
  autoCompleteButton.addEventListener("click", () =>
    sendAction("/auto-complete"),
  );

  render();
})();
