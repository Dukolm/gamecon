import { MyStateCreator } from ".";
import { sleep } from "../utils";

export type ExmampleSlice = {
  example: {
    value: number,
    setValue(value: number): Promise<void>,
    increaseValue(): void,
  }
};

export const createExampleSlice: MyStateCreator<ExmampleSlice> = (set, get) => ({
  example: {
    value: 0,
    async setValue(value) {
      await sleep(200);
      // Provedu změnu na imutabilním objektu kterou propíše immer
      set(s => { s.example.value = value; });
    },
    increaseValue() {
      const newValue = get().example.value+1;
      set(s=>{s.example.value = newValue});
      // Totožné s set(s=>{s.example.value++;}) ale umožňuje větší kontrolu nad operacemi pomocí použítí get()
    },
  },
});
