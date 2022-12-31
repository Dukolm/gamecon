import { FunctionComponent } from "preact";
import { ProgramNáhled } from "./components/náhled/ProgramNáhled";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";

import "./program.less";

export const Program: FunctionComponent = () => {
  return (
    <div style={{ position: "relative" }}>
      <ProgramNáhled />
      <ProgramUživatelskéVstupy />
      <ProgramLegenda />
      <ProgramTabulka />
    </div>
  );
};
