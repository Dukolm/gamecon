import { FunctionComponent } from "preact";
import { GAMECON_KONSTANTY } from "../../env";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";

import "./program.less";
import { ProgramNáhled } from "./components/náhled/ProgramNáhled";
import { useEffect } from "preact/hooks";
import { useProgramStore } from "../../store/program";

/** část odazu od které začíná programově specifické url managované preactem */
export const PROGRAM_URL_NAME = "program";

export const Program: FunctionComponent = () => {
  const urlState = useProgramStore(s=>s.urlState);
  const načtiRok = useProgramStore(s=>s.načtiRok);

  const aktivity = useProgramStore(
    (s) => s.data.aktivityPodleRoku[GAMECON_KONSTANTY.ROK] || []
  );

  const aktivitaNáhled =
    urlState.aktivitaNáhledId !== undefined
      ? aktivity.find((x) => x.id === urlState.aktivitaNáhledId)
      : undefined;

  useEffect(() => {
    void načtiRok(GAMECON_KONSTANTY.ROK);
  }, []);

  return (
    <div style={{ position: "relative" }}>
      {aktivitaNáhled ? (
        <ProgramNáhled aktivita={aktivitaNáhled} />
      ) : undefined}
      <ProgramUživatelskéVstupy />
      <ProgramLegenda />
      <ProgramTabulka {...{ aktivity: aktivity }} />
    </div>
  );
};
