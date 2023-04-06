import { FunctionComponent } from "preact";
import { formátujDatum } from "@gamecon/core/src/utils/index";
import produce from "immer";
import {
  generujUrl,
  porovnejTabulkaVýběr,
} from "../../store/logic/url";
import { nastavUrlVýběr } from "../../store/slices/urlSlice";
import {
  useFiltryOtevřené,
  useUrlState,
  useUrlStateMožnostiDny,
} from "../../store/selektory";
import { Filtry } from "./Filtry";
import { GAMECON_KONSTANTY } from "@gamecon/api/src/env";
import { nastavFiltryOtevřené } from "../../store/slices/všeobecnéSlice";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const {} = props;
  const urlState = useUrlState();
  const urlStateMožnosti = useUrlStateMožnostiDny();

  const jeLetošníRočník = urlState.ročník === GAMECON_KONSTANTY.ROCNIK;


  const filtryOtevřené = useFiltryOtevřené();

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {urlState.ročník}</h1>
        <div class="clearfix">
          <div class="program_dny">
            {urlStateMožnosti.map((možnost) => {
              return (
                <a
                  href={generujUrl(
                    produce(urlState, (s) => {
                      s.výběr = možnost;
                    })
                  )}
                  class={
                    "program_den" +
                    (porovnejTabulkaVýběr(možnost, urlState.výběr)
                      ? " program_den-aktivni"
                      : "")
                  }
                  onClick={(e) => {
                    e.preventDefault();
                    nastavUrlVýběr(možnost);
                  }}
                >
                  {možnost.typ === "můj"
                    ? "můj program"
                    : formátujDatum(možnost.datum, !jeLetošníRočník)}
                </a>
              );
            })}
          </div>
          <button
            class={"program_filtry_tlacitko" + (filtryOtevřené ? " aktivni" : "")}
            onClick={() => {
              nastavFiltryOtevřené(!filtryOtevřené);
            }}
          >
            Filtry
          </button>
        </div>
        <Filtry {...{otevřeno: filtryOtevřené}}/>
      </div>
    </>
  );
};
