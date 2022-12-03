import type { Aktivita} from "../../api/program";
import type { FunctionComponent } from "preact";
import { GAMECON_KONSTANTY } from "../../env";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramURLState, useProgramSemanticRoute } from "./routing";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";

import "./program.less";
import { ProgramNáhled } from "./components/náhled/ProgramNáhled";
import { useEffect, useState } from "preact/hooks";
import { fetchAktivity } from "../../api/program";

/** část odazu od které začíná programově specifické url managované preactem */
export const PROGRAM_URL_NAME = "program";

export const Program: FunctionComponent = () => {
  const semanticRoute = useProgramSemanticRoute();
  const { urlState } = semanticRoute;

  const [aktivity, setAktivity] = useState<Aktivita[]>([]);

  const aktivitaNáhled =
    urlState.aktivitaNáhledId !== undefined
      ? aktivity.find((x) => x.id === urlState.aktivitaNáhledId)
      : undefined;

  useEffect(() => {
    void (async () => {
      const aktivity = await fetchAktivity(GAMECON_KONSTANTY.ROK);
      setAktivity(aktivity);
    })();
  }, []);

  return (
    <ProgramURLState.Provider value={semanticRoute}>
      <div style={{ position: "relative" }}>
        {aktivitaNáhled ? (
          <ProgramNáhled aktivita={aktivitaNáhled} />
        ) : undefined}
        <ProgramUživatelskéVstupy />
        <ProgramLegenda />
        <ProgramTabulka {...{ aktivity }} />
      </div>
    </ProgramURLState.Provider>
  );
};
