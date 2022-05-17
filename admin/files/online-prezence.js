(function ($) {
  $(function () {

    /*
    Ve Firefoxu je zvláštní chyba, kdy pokud se checkbox změní na checked pomocí JS, poté se stránka přenačte, backend stránku
    pošle bez checked (což obvykle znamená "nezaškrtnuto"), tak Firefox ponechá zaškrtnutí z předchozí akce JS.
    Toto je workaround.
     */
    $('input.dorazil[type=checkbox]').each(function (index, checkbox) {
      if (!checkbox.dataset.initialChecked) {
        checkbox.checked = false
      }
    })

    // ZMENA METADAT PREZENCE UCASTNIKA

    const $aktivity = $('.aktivita')

    $aktivity.each(function (index, aktivitaNode) {
      aktivitaNode.addEventListener('novyUcastnik', function (/** @param {{detail: {idAktivity: number, idUzivatele: number}}} event */event) {
        hlidejNovehoUcastnika(event.detail.idUzivatele, event.detail.idAktivity)
      })
      Array.from(aktivitaNode.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (tooltip) {
        new bootstrap.Tooltip(tooltip)
      })
    })

    $('.ucastnik').each(function (index, ucastnikNode) {
      hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(ucastnikNode.dataset.id, ucastnikNode.dataset.idAktivity)
    })

    /**
     * @param {number|string} idUzivatele
     * @param {number|string} idAktivity
     */
    function hlidejNovehoUcastnika(idUzivatele, idAktivity) {
      hlidejZmenyMetadatUcastnika(dejNodeUcastnika(idUzivatele, idAktivity))
      aktivujTooltipUcastnika(idUzivatele, idAktivity)
    }

    /**
     * @param {number|string} idUzivatele
     * @param {number|string} idAktivity
     * @return {HTMLElement}
     */
    function dejNodeUcastnika(idUzivatele, idAktivity) {
      return document.getElementById(`ucastnik-${idUzivatele}-na-aktivite-${idAktivity}`)
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {string} stavPrihlaseni
     */
    function zobrazTypUcastnika(ucastnikNode, stavPrihlaseni) {
      const idUzivatele = ucastnikNode.dataset.id
      const idAktivity = ucastnikNode.dataset.idAktivity
      const jeNahradnik = document.getElementById(`ucastik-${idUzivatele}-je-nahradnik-na-aktivite-${idAktivity}`)
      const jeSledujici = document.getElementById(`ucastik-${idUzivatele}-je-sledujici-aktivity-${idAktivity}`)
      switch (stavPrihlaseni) {
        case 'sledujici_se_prihlasil' :
          skryt(jeNahradnik)
          zobrazit(jeSledujici)
          break
        case 'nahradnik_dorazil' :
        case 'nahradnik_nedorazil' :
          skryt(jeSledujici)
          zobrazit(jeNahradnik)
          break
        default :
          skryt(jeNahradnik)
          skryt(jeSledujici)
      }
    }

    /**
     * @param {HTMLElement} node
     */
    function skryt(node) {
      node.classList.add('display-none')
    }

    /**
     * @param {HTMLElement} node
     */
    function zobrazit(node) {
      node.classList.remove('display-none')
    }

    /**
     * @param {number} idUzivatele
     * @param {number} idAktivity
     */
    function aktivujTooltipUcastnika(idUzivatele, idAktivity) {
      const tooltipTriggerList = Array.from(document.querySelectorAll(`#ucastnik-${idUzivatele}-na-aktivite-${idAktivity} [data-bs-toggle="tooltip"]`))
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl)
      })
    }

    /**
     * @param {HTMLElement} ucastnikNode
     */
    function hlidejZmenyMetadatUcastnika(ucastnikNode) {
      ucastnikNode.addEventListener('zmenaMetadatPrezence', function (/** @param {{detail: {casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number}}} event */event) {
        zapisMetadataPrezence(ucastnikNode, event.detail)
        zobrazTypUcastnika(ucastnikNode, event.detail.stavPrihlaseni)
      })
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {{casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number, callback: function|undefined}} metadataPrezence
     */
    function zapisMetadataPrezence(ucastnikNode, metadataPrezence) {
      if (ucastnikNode.dataset.idPoslednihoLogu && Number(ucastnikNode.dataset.idPoslednihoLogu) >= metadataPrezence.idPoslednihoLogu) {
        return // změna je stejná nebo dokonce starší, než už známe
      }
      ucastnikNode.dataset.casPosledniZmenyPrihlaseni = metadataPrezence.casPosledniZmenyPrihlaseni
      ucastnikNode.dataset.stavPrihlaseni = metadataPrezence.stavPrihlaseni
      ucastnikNode.dataset.idPoslednihoLogu = metadataPrezence.idPoslednihoLogu.toString()

      if (typeof metadataPrezence.callback === 'function') {
        metadataPrezence.callback()
      }
    }

    // ZOBRAZENI ERRORS
    Array.from(document.getElementsByClassName('aktivita')).forEach(function (aktivita) {
      aktivita.addEventListener('ajaxErrors', function (/** @param {{detail: {errors: string[]}}} event */event) {
        if (event.detail.errors) {
          const errorTemplate = aktivita.getElementsByClassName('error-template')[0]
          event.detail.errors.forEach(function (errorText) {
            /** @var {HTMLElement} */
            const errorNode = errorTemplate.cloneNode(true)
            const errorTextNode = errorNode.getElementsByClassName('error-text')[0]
            errorTextNode.innerHTML = errorText
            errorTemplate.parentElement.appendChild(errorNode)
            errorNode.classList.remove('display-none')
          })
        }
      })
    })

    /**
     * Bude zpracováno v event listeneru přes hlidejNovehoUcastnika()
     * @param {number} idUzivatele
     * @param {number} idAktivity
     */
    function vypustEventONovemUcastnikovi(idUzivatele, idAktivity) {
      const novyUcastnik = new CustomEvent('novyUcastnik', {
        detail: {
          idAktivity: idAktivity, idUzivatele: idUzivatele,
        },
      })
      document.getElementById(`aktivita-${idAktivity}`).dispatchEvent(novyUcastnik)
    }


    // OMNIBOX
    intializePrezenceOmnibox()

    function intializePrezenceOmnibox() {
      const omnibox = $('.online-prezence .omnibox')
      omnibox.on('autocompleteselect', function (event, ui) {
        const idAktivity = Number(this.dataset.idAktivity)
        const idUzivatele = Number(ui.item.value)
        const ucastniciAktivityNode = $(`#ucastniciAktivity${idAktivity}`)
        const novyUcastnik = $(ui.item.html)

        zmenitPritomnostUcastnika(idUzivatele, idAktivity, novyUcastnik.find('input')[0], function () {
          /**
           * Teprve až backend potvrdí uložení vybraného účastníka a JS přidá čas poslední změny a stav přihlášení,
           * tak můžeme přidat řádek s tímto účastníkem.
           * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
           */
          ucastniciAktivityNode.append(novyUcastnik)
          vypustEventONovemUcastnikovi(idUzivatele, idAktivity)
        })

        // vyrušení default výběru do boxu
        event.preventDefault()
        $(this).val('')

        // skrytí výchozí oklivé hlášky
        $('.ui-helper-hidden-accessible').hide()
      })

      omnibox.on('autocompleteresponse', function (event, ui) {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxHledam${idAktivity}`).hide()
        if (!ui || ui.content === undefined || ui.content.length === 0) {
          $(`#omniboxNicNenalezeno${idAktivity}`).show()
        } else {
          $(`#omniboxNicNenalezeno${idAktivity}`).hide()
        }
      })

      omnibox.on('input', function () {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxNicNenalezeno${idAktivity}`).hide()
        const minLength = this.dataset.omniboxMinLength
        const length = this.value.length
        if (minLength <= length) {
          $(`#omniboxHledam${idAktivity}`).show()
        }
      })

      $('.formAktivita').submit(function () {
        const $aktivita = $(this).closest('.blokAktivita')
        // test na vyplnění políček / potvrzení
        const policek = $aktivita.find('[type=checkbox]').length
        const vybrano = $aktivita.find('[type=checkbox]:checked').length
        if (vybrano < policek / 2) {
          if (!confirm('Opravdu uložit s účastí menší jak polovina?')) {
            return false
          }
        }
        // odeslání
        $aktivita.find('[type=submit]').attr('disabled', true)
        $aktivita.load(document.URL + ' .blokAktivita[data-id=' + $aktivita.data('id') + '] > *', $(this).serializeObject(), function () {
          initializeOmnibox($)
          intializePrezenceOmnibox()
        })
        return false
      })
    }

    // ⏳ ČEKÁNÍ NA EDITACI ⏳

    $aktivity.each(function () {
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-ceka .odpocet').each(function () {
        if ($(this).data('editovatelna-od') > 0) {
          zablokovatAktivituProEditaciSOdpoctem($aktivitaNode.data('id'))
        }
      })
    })

    function zablokovatAktivituProEditaciSOdpoctem(idAktivity) {
      const $aktivitaNode = $(`#aktivita-${idAktivity}`)
      $aktivitaNode.find('input').prop('disabled', true)
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').hide()
      $aktivitaNode.find('.text-ceka').show()
      spustitOdpocet($aktivitaNode, idAktivity)
    }

    function spustitOdpocet(aktivitaNode, idAktivity) {
      const $odpocetNode = aktivitaNode.find(`#odpocet-${idAktivity}`)
      const editovatelnaOdTimestamp = Number.parseInt($odpocetNode.data('editovatelna-od'))

      if (dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {object} $odpocetNode
     * @param idAktivity
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp) {
      if (obnovitOdpocet($odpocetNode, editovatelnaOdTimestamp)) {
        return false // ještě nemůžeme odpočet dokončit, stále musí běžet
      }
      odblokovatAktivituProEditaci(idAktivity)
      return true
    }

    function odblokovatAktivituProEditaci(idAktivity) {
      const akivitaNode = $(`#aktivita-${idAktivity}`)
      akivitaNode.find('input').prop('disabled', false)
      akivitaNode.find('.text-ceka').hide()
      akivitaNode.find('.tlacitko-uzavrit-aktivitu').show()
    }

    /**
     * @param {object} odpocetNode
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function obnovitOdpocet(odpocetNode, editovatelnaOdTimestamp) {
      const zbyvaSekund = spoctiKolikZbyvaSekund(editovatelnaOdTimestamp)

      if (zbyvaSekund <= 0) {
        return false
      }

      odpocetNode.text(sekundyNaLidskyCas(zbyvaSekund))
      return true
    }

    /**
     * @param {number} sekundy
     * @return {string}
     */
    function sekundyNaLidskyCas(sekundy) {
      const sekundVeDni = 3600 * 24
      const zbyvaDni = Math.floor(sekundy / sekundVeDni)
      const sekundyBezDni = sekundy - (zbyvaDni * sekundVeDni)
      const zbyvaHodin = Math.floor(sekundyBezDni / 3600)
      const sekundyBezDniAHodin = sekundyBezDni - (zbyvaHodin * 3600)
      const zbyvaMinut = Math.floor(sekundyBezDniAHodin / 60)
      const zbyvaSekund = sekundyBezDniAHodin - (zbyvaMinut * 60)

      let lidskyCas = ''
      if (zbyvaDni) {
        lidskyCas += `${zbyvaDni} d`
      }
      if (zbyvaDni || zbyvaHodin) {
        lidskyCas += `${zbyvaHodin} h`
      }
      if (zbyvaDni || zbyvaHodin || zbyvaMinut) {
        lidskyCas += ` ${zbyvaMinut} m`
      }
      if (zbyvaDni || zbyvaHodin || zbyvaMinut || zbyvaSekund) {
        lidskyCas += ` ${zbyvaSekund} s`
      }

      return lidskyCas
    }

    /**
     * @param {number} unixTimestampInSeconds
     * @return {number}
     */
    function spoctiKolikZbyvaSekund(unixTimestampInSeconds) {
      return Math.round(unixTimestampInSeconds - getNowAsUnixTimestampInSeconds())
    }

    /**
     * @return {number}
     */
    function getNowAsUnixTimestampInSeconds() {
      return new Date().getTime() / 1000
    }

    // ✋ AKTIVITA UŽ SKONČILA, POZOR NA ÚPRAVY ✋
    $aktivity.each(function () {
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-skoncila').each(function () {
        if (this.classList.contains('display-none')) {
          return // etxt
        }
        const $textSkoncilaNode = $(this)
        hlidatUpozorneniNaSkoncenouAktivitu($textSkoncilaNode)
      })
    })

    function hlidatUpozorneniNaSkoncenouAktivitu($textSkoncilaNode) {
      const konecAktivityVTimestamp = Number.parseInt($textSkoncilaNode.data('konec-aktivity-v'))
      if (!konecAktivityVTimestamp) {
        return
      }

      if (zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp)) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp)) {
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {object} $textSkoncilaNode
     * @param {number} konecAktivityVTimestamp
     */
    function zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp) {
      const konecZaSekund = spoctiKolikZbyvaSekund(konecAktivityVTimestamp)
      if (konecZaSekund > 0) {
        return false
      }
      $textSkoncilaNode.show()
      return true
    }
  })
})(jQuery)

/**
 * @param {number} idUzivatele
 * @param {number} idAktivity
 * @param {HTMLElement} checkboxNode
 * @param {function|undefined} callbackOnSuccess
 */
function zmenitPritomnostUcastnika(idUzivatele, idAktivity, checkboxNode, callbackOnSuccess) {
  checkboxNode.disabled = true
  dorazil = checkboxNode.checked
  $.post(location.href, {
    /**
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxZmenitPritomnostUcastnika
     */
    akce: 'zmenitPritomnostUcastnika',
    idAktivity: idAktivity,
    idUzivatele: idUzivatele,
    dorazil: dorazil ? 1 : 0,
    ajax: 1,
  }).done(/** @param {void|{prihlasen: boolean, cas_posledni_zmeny_prihlaseni: string, stav_prihlaseni: string, id_logu: string}} data */function (data) {
    checkboxNode.disabled = false
    if (data && typeof data.prihlasen == 'boolean') {
      checkboxNode.checked = data.prihlasen

      const zmenaMetadatPrezence = new CustomEvent('zmenaMetadatPrezence', {
        detail: {
          casPosledniZmenyPrihlaseni: data.cas_posledni_zmeny_prihlaseni,
          stavPrihlaseni: data.stav_prihlaseni,
          idPoslednihoLogu: data.id_logu,
        },
      })
      const ucastnikNode = $(checkboxNode).parents('.ucastnik')[0]
      // bude zpracovano v zapisMetadataPrezence()
      ucastnikNode.dispatchEvent(zmenaMetadatPrezence)

      if (callbackOnSuccess) {
        callbackOnSuccess()
      }
    }
  }).fail(function (response) {
    if (response.status === 400 && response.responseJSON && response.responseJSON.errors) {
      const errorsEvent = new CustomEvent('ajaxErrors', {detail: {errors: response.responseJSON.errors}})
      document.getElementById(`aktivita-${idAktivity}`).dispatchEvent(errorsEvent)
    }
  })
}

const akceAktivity = new class AkceAktivity {

  /**
   * @public
   * @param {number} idAktivity
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
    const that = this
    $.post(location.href, {
      akce: 'uzavrit', id: idAktivity, ajax: true,
    }).done(function (/** @param {{maPravoNaZmenuHistorieAktivit: boolean}} data */data) {
      that.prohoditZobrazeni(skrytElement, zobrazitElement)
      if (data.maPravoNaZmenuHistorieAktivit) {
        that.zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity)
      } else {
        that.zablokovatEditaciAktivity(idAktivity)
      }
    })
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatEditaciAktivity(idAktivity) {
    this.zablokovatInputyAktivity(idAktivity)
    $(`.skryt-pokud-aktivitu-nelze-editovat-${idAktivity}`).hide()
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatInputyAktivity(idAktivity) {
    const aktivitaNode = $(`#aktivita-${idAktivity}`)
    aktivitaNode.find('input').prop('disabled', true)
  }

  /**
   * @private
   * @param idAktivity
   */
  zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity) {
    $(`#pozor-vyplnena-${idAktivity}`).show()
  }

  /**
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElement, zobrazitElement) {
    skrytElement.style.display = 'none'
    zobrazitElement.style.display = 'initial'
  }
}

/**
 * @param {number} idAktivity
 * @param {HTMLElement} skrytElement
 * @param {HTMLElement} zobrazitElement
 */
function uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
  akceAktivity.uzavritAktivitu(idAktivity, skrytElement, zobrazitElement)
}

/**
 * @param {HTMLElement} skrytElement
 * @param {HTMLElement} zobrazitElement
 */
function prohoditZobrazeni(skrytElement, zobrazitElement) {
  akceAktivity.prohoditZobrazeni(skrytElement, zobrazitElement)
}