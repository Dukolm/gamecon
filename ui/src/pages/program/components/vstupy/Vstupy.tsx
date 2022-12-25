import { FunctionComponent } from "preact";
import { GAMECON_KONSTANTY } from "../../../../env";
import { useProgramStore } from "../../../../store/program";
import { porovnejTabulkaVýběr } from "../../../../store/program/url";
import { formátujDatum } from "../../../../utils";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const urlState = useProgramStore((s) => s.urlState);

  const rok = GAMECON_KONSTANTY.ROK;

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {rok}</h1>
        <div class="program_dny">
          {urlState.možnosti.map((možnost) => {
            return (
              <button
                class={
                  "program_den" +
                  (porovnejTabulkaVýběr(možnost, urlState.výběr)
                    ? " program_den-aktivni"
                    : "")
                }
                onClick={() => {
                  useProgramStore.setState((s) => {
                    s.urlState.výběr = možnost;
                  }, undefined, "nastav program den");
                }}
              >
                {možnost.typ === "můj"
                  ? "můj program"
                  : formátujDatum(možnost.datum)}
              </button>
            );
          })}
        </div>
      </div>
    </>
  );
};
