import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { ProgramNáhled } from "./components/ProgramNáhled";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { inicializujProgramStore } from "../../store/program/inicializace";
import { načtiPřihlášenýUživatel } from "../../store/program/slices/přihlášenSlice";

import "./program.less";

export const Program: FunctionComponent = () => {
  useEffect(inicializujProgramStore, []);

  useEffect(() => {
    void načtiPřihlášenýUživatel();
  }, []);

  return (
    <div style={{ position: "relative" }}>
      <ProgramNáhled />
      <ProgramUživatelskéVstupy />
      <ProgramLegenda />
      <ProgramTabulka />
    </div>
  );
};
